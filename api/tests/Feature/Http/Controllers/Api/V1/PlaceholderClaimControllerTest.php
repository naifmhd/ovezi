<?php

use App\ContactType;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Placeholder;
use App\Models\User;
use App\Services\PlaceholderContactNormalizer;

function placeholderForEmail(User $creator, string $email, string $name = 'Guest Person'): Placeholder
{
    $normalizer = app(PlaceholderContactNormalizer::class);
    $normalized = $normalizer->normalize(ContactType::Email, $email);

    return Placeholder::factory()->for($creator, 'creator')->create([
        'name' => $name,
        'contact_type' => ContactType::Email,
        'contact_value' => $normalized,
        'contact_hash' => $normalizer->hash($normalized),
    ]);
}

it('shows every unclaimed placeholder matching the verified email', function () {
    $claimant = User::factory()->create(['email' => 'person@example.com']);
    $firstCreator = User::factory()->create();
    $secondCreator = User::factory()->create();
    $first = placeholderForEmail($firstCreator, 'PERSON@example.com', 'First history');
    $second = placeholderForEmail($secondCreator, 'person@example.com', 'Second history');
    placeholderForEmail($firstCreator, 'someone-else@example.com', 'Not a match');
    $phoneNormalizer = app(PlaceholderContactNormalizer::class);
    $phone = $phoneNormalizer->normalize(ContactType::Phone, '+9607000000');
    Placeholder::factory()->for($secondCreator, 'creator')->create([
        'contact_type' => ContactType::Phone,
        'contact_value' => $phone,
        'contact_hash' => $phoneNormalizer->hash($phone),
    ]);

    $response = $this->withToken($claimant->createToken('Claim phone')->plainTextToken)
        ->getJson('/api/v1/placeholder-claims');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toBe([$first->id, $second->id]);
});

it('requires a verified email before showing or claiming matches', function () {
    $claimant = User::factory()->unverified()->create(['email' => 'person@example.com']);
    $placeholder = placeholderForEmail(User::factory()->create(), $claimant->email);
    $token = $claimant->createToken('Claim phone')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/placeholder-claims')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
    $this->withToken($token)
        ->postJson("/api/v1/placeholder-claims/{$placeholder->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});

it('claims history atomically and aggregates its group balance under the user', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $claimant = User::factory()->create([
        'email' => 'person@example.com',
        'default_currency_code' => $currency->code,
    ]);
    $placeholder = placeholderForEmail($owner, $claimant->email, 'Old guest name');
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    $placeholderMembership = GroupMember::factory()->for($group)->for($placeholder)->create([
        'user_id' => null,
        'joined_at' => now()->subMonth(),
    ]);
    $expense = Expense::factory()->for($group)->create([
        'expense_type' => 'group',
        'payer_user_id' => $owner->id,
        'created_by' => $owner->id,
        'amount_minor' => 1000,
        'currency_code' => $currency->code,
        'reporting_amount_minor' => 1000,
        'reporting_currency_code' => $currency->code,
    ]);
    ExpenseSplit::factory()->for($expense)->for($owner)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);
    $placeholderSplit = ExpenseSplit::factory()->for($expense)->for($placeholder)->create([
        'user_id' => null,
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);
    $token = $claimant->createToken('Claim phone')->plainTextToken;

    $this->withToken($token)
        ->postJson("/api/v1/placeholder-claims/{$placeholder->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $placeholder->id)
        ->assertJsonPath('data.is_claimed', true);

    expect($placeholder->fresh()->claimed_by)->toBe($claimant->id)
        ->and($placeholderMembership->fresh()->left_at)->not->toBeNull()
        ->and($placeholderSplit->fresh()->placeholder_id)->toBe($placeholder->id)
        ->and($placeholderSplit->fresh()->user_id)->toBeNull();
    $this->assertDatabaseHas('group_members', [
        'group_id' => $group->id,
        'user_id' => $claimant->id,
        'left_at' => null,
    ]);

    $balanceResponse = $this->withToken($token)
        ->getJson("/api/v1/groups/{$group->id}/balances")
        ->assertOk();
    $claimantBalance = collect($balanceResponse->json('data.members'))
        ->firstWhere('participant.user_id', $claimant->id);
    expect($claimantBalance['balance_minor'])->toBe(-500);
});

it('makes claimed direct expense history visible without rewriting it', function () {
    $currency = Currency::factory()->mvr()->create();
    $creator = User::factory()->create(['default_currency_code' => $currency->code]);
    $claimant = User::factory()->create([
        'email' => 'person@example.com',
        'default_currency_code' => $currency->code,
    ]);
    $placeholder = placeholderForEmail($creator, $claimant->email);
    $expense = Expense::factory()->create([
        'expense_type' => 'direct',
        'payer_user_id' => $creator->id,
        'created_by' => $creator->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);
    ExpenseSplit::factory()->for($expense)->for($creator)->create();
    ExpenseSplit::factory()->for($expense)->for($placeholder)->create(['user_id' => null]);
    $token = $claimant->createToken('Claim phone')->plainTextToken;

    $this->withToken($token)->getJson("/api/v1/expenses/{$expense->id}")->assertForbidden();
    $this->withToken($token)
        ->postJson("/api/v1/placeholder-claims/{$placeholder->id}")
        ->assertOk();
    $this->withToken($token)
        ->getJson("/api/v1/expenses/{$expense->id}")
        ->assertOk()
        ->assertJsonPath('data.splits.1.placeholder_id', $placeholder->id)
        ->assertJsonPath('data.splits.1.claimed_user_id', $claimant->id);
});

it('does not allow claiming a placeholder belonging to another verified email', function () {
    $claimant = User::factory()->create(['email' => 'person@example.com']);
    $placeholder = placeholderForEmail(User::factory()->create(), 'other@example.com');

    $this->withToken($claimant->createToken('Claim phone')->plainTextToken)
        ->postJson("/api/v1/placeholder-claims/{$placeholder->id}")
        ->assertNotFound();

    expect($placeholder->fresh()->claimed_by)->toBeNull();
});
