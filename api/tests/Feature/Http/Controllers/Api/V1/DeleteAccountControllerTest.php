<?php

use App\Actions\DeleteAccount;
use App\ConnectedAccountProvider;
use App\ExpenseType;
use App\GroupMemberRole;
use App\Jobs\PurgeDeletedExpenses;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Services\Social\SocialIdentityVerifier;
use App\Services\Social\VerifiedSocialIdentity;
use Illuminate\Support\Facades\Queue;

it('anonymizes an eligible account, revokes tokens, and preserves memberships', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $user = User::factory()->create([
        'default_currency_code' => $currency->code,
        'password' => 'current-password-value',
    ]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    $membership = GroupMember::factory()->for($group)->for($user)->create();
    $token = $user->createToken('User phone');
    $user->createToken('Tablet');

    $this->withToken($token->plainTextToken)->deleteJson('/api/v1/me', [
        'current_password' => 'current-password-value',
    ])->assertNoContent();

    $this->assertSoftDeleted('users', ['id' => $user->id]);
    $this->assertDatabaseHas('group_members', ['id' => $membership->id, 'user_id' => $user->id]);
    $this->assertDatabaseCount('personal_access_tokens', 0);
    expect(User::withTrashed()->find($user->id)->name)->toBe('Deleted member');
    expect(User::withTrashed()->find($user->id)->email)->not->toBe($user->email);
});

it('archives a sole owned group when its owner deletes their account', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create([
        'default_currency_code' => $currency->code,
        'password' => 'current-password-value',
    ]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    $token = $owner->createToken('Owner phone');

    $this->withToken($token->plainTextToken)->deleteJson('/api/v1/me', [
        'current_password' => 'current-password-value',
    ])->assertNoContent();

    $this->assertSoftDeleted('users', ['id' => $owner->id]);
    expect($group->fresh()->archived_at)->not->toBeNull();
});

it('preserves shared balances when a member deletes their account', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $user = User::factory()->create([
        'default_currency_code' => $currency->code,
        'password' => 'current-password-value',
    ]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    GroupMember::factory()->for($group)->for($user)->create();
    $expense = Expense::factory()->create([
        'expense_type' => ExpenseType::Group,
        'group_id' => $group->id,
        'payer_user_id' => $owner->id,
        'amount_minor' => 1000,
        'currency_code' => $currency->code,
        'reporting_amount_minor' => 1000,
        'reporting_currency_code' => $currency->code,
        'created_by' => $owner->id,
    ]);
    ExpenseSplit::factory()->for($expense)->for($owner)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);
    ExpenseSplit::factory()->for($expense)->for($user)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);
    $token = $user->createToken('User phone');

    $this->withToken($token->plainTextToken)->deleteJson('/api/v1/me', [
        'current_password' => 'current-password-value',
    ])->assertNoContent();

    $this->assertSoftDeleted('users', ['id' => $user->id]);
    app('auth')->forgetGuards();
    $this->withToken($owner->createToken('owner-phone')->plainTextToken)
        ->getJson("/api/v1/groups/{$group->id}/balances")->assertOk()
        ->assertJsonFragment(['balance_minor' => 500])->assertJsonFragment(['balance_minor' => -500])
        ->assertJsonFragment(['name' => 'Deleted member']);
    $this->getJson("/api/v1/groups/{$group->id}")->assertOk()->assertJsonFragment(['is_deleted' => true]);
    $this->postJson("/api/v1/groups/{$group->id}/settlements", [
        'from_user_id' => $user->id, 'to_user_id' => $owner->id, 'amount_minor' => 500,
        'currency_code' => 'MVR', 'occurred_at' => now()->toISOString(),
    ])->assertCreated();
    $this->getJson("/api/v1/groups/{$group->id}/balances")->assertOk()->assertJsonCount(0, 'data.suggested_settlements');
});

it('preserves direct history when a member deletes their account', function () {
    $currency = Currency::factory()->mvr()->create();
    $user = User::factory()->create([
        'default_currency_code' => $currency->code,
        'password' => 'current-password-value',
    ]);
    $friend = User::factory()->create(['default_currency_code' => $currency->code]);
    Friendship::factory()->accepted()->create(['user_id' => $user->id, 'friend_id' => $friend->id, 'requested_by' => $user->id]);
    $expense = Expense::factory()->create([
        'expense_type' => ExpenseType::Direct,
        'group_id' => null,
        'payer_user_id' => $friend->id,
        'amount_minor' => 1000,
        'currency_code' => $currency->code,
        'reporting_amount_minor' => 1000,
        'reporting_currency_code' => $currency->code,
        'created_by' => $user->id,
    ]);
    ExpenseSplit::factory()->for($expense)->for($friend)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);
    ExpenseSplit::factory()->for($expense)->for($user)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);
    $token = $user->createToken('User phone');

    $this->withToken($token->plainTextToken)->deleteJson('/api/v1/me', [
        'current_password' => 'current-password-value',
    ])->assertNoContent();

    $this->assertSoftDeleted('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    app('auth')->forgetGuards();
    $this->withToken($friend->createToken('friend-phone')->plainTextToken)->getJson('/api/v1/friends')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/balances')->assertOk()->assertJsonPath('data.direct.0.participant.name', 'Deleted member')->assertJsonPath('data.direct.0.balance_minor', 500);
    $this->postJson('/api/v1/settlements', ['from_user_id' => $user->id, 'to_user_id' => $friend->id,
        'amount_minor' => 500, 'currency_code' => 'MVR', 'reporting_currency_code' => 'MVR', 'occurred_at' => now()->toISOString(),
    ])->assertCreated();
});

it('requires the current password', function () {
    $user = User::factory()->create(['password' => 'current-password-value']);
    $token = $user->createToken('User phone');

    $this->withToken($token->plainTextToken)->deleteJson('/api/v1/me', [
        'current_password' => 'incorrect-password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');
});

it('deletes a passwordless account only after a fresh matching provider confirmation', function () {
    $user = User::factory()->create(['password' => null]);
    $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'same-user']);
    $identity = new VerifiedSocialIdentity(ConnectedAccountProvider::Google, 'same-user', $user->email, true, null, null, now()->timestamp);
    $this->mock(SocialIdentityVerifier::class)->shouldReceive('verify')->once()->andReturn($identity);
    $this->withToken($user->createToken('phone')->plainTextToken)->deleteJson('/api/v1/me', ['provider' => 'google', 'id_token' => 'verified-provider-token'])->assertNoContent();
    $this->assertDatabaseMissing('social_accounts', ['user_id' => $user->id]);
    $this->assertSoftDeleted('users', ['id' => $user->id]);
});

it('rejects a provider identity belonging to another account', function () {
    $user = User::factory()->create(['password' => null]);
    $user->socialAccounts()->create(['provider' => 'google', 'provider_user_id' => 'right-user']);
    $identity = new VerifiedSocialIdentity(ConnectedAccountProvider::Google, 'wrong-user', $user->email, true, null, null, now()->timestamp);
    $this->mock(SocialIdentityVerifier::class)->shouldReceive('verify')->once()->andReturn($identity);
    $this->withToken($user->createToken('phone')->plainTextToken)->deleteJson('/api/v1/me', ['provider' => 'google', 'id_token' => 'wrong-token'])->assertUnprocessable()->assertJsonValidationErrors('id_token');
    $this->assertNotSoftDeleted('users', ['id' => $user->id]);
});

it('transfers ownership to a live member and erases personal recurring templates', function () {
    Queue::fake([PurgeDeletedExpenses::class]);
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => 'MVR']);
    $deleted = User::factory()->create();
    $deleted->delete();
    $successor = User::factory()->create();
    $group = Group::factory()->for($owner, 'creator')->create(['reporting_currency_code' => 'MVR']);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    GroupMember::factory()->for($group)->for($deleted)->create();
    $member = GroupMember::factory()->for($group)->for($successor)->create();
    $schedule = RecurringExpense::factory()->create(['created_by' => $owner->id, 'payer_user_id' => $owner->id, 'expense_type' => ExpenseType::Personal, 'currency_code' => 'MVR']);
    $personal = Expense::factory()->create(['expense_type' => ExpenseType::Personal, 'created_by' => $owner->id, 'payer_user_id' => $owner->id, 'currency_code' => 'MVR', 'reporting_currency_code' => 'MVR', 'recurring_expense_id' => $schedule->id, 'recurring_occurrence_on' => today()]);
    app(DeleteAccount::class)->deleteVerified($owner);
    expect($member->fresh()->role)->toBe(GroupMemberRole::Owner);
    $this->assertDatabaseMissing('recurring_expenses', ['id' => $schedule->id]);
    $this->assertSoftDeleted('expenses', ['id' => $personal->id, 'recurring_expense_id' => null]);
});
