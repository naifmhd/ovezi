<?php

use App\ExpenseType;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

it('preserves a zero-balance member as inactive history', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $member = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    $membership = GroupMember::factory()->for($group)->for($member)->create();
    $token = $owner->createToken('Owner phone');

    $response = $this->withToken($token->plainTextToken)
        ->deleteJson("/api/v1/groups/{$group->id}/members/{$membership->id}");

    $response
        ->assertOk()
        ->assertJsonPath('data.id', $membership->id)
        ->assertJsonPath('data.user.id', $member->id);
    expect($membership->fresh()->left_at)->not->toBeNull();
    $this->assertDatabaseHas('activity_logs', [
        'group_id' => $group->id,
        'actor_id' => $owner->id,
        'subject_id' => $membership->id,
        'event' => 'member.removed',
    ]);
});

it('blocks removal while the member has an open balance', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $member = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    $membership = GroupMember::factory()->for($group)->for($member)->create();
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
    ExpenseSplit::factory()->for($expense)->for($member)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);
    $token = $owner->createToken('Owner phone');

    $this->withToken($token->plainTextToken)
        ->deleteJson("/api/v1/groups/{$group->id}/members/{$membership->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('member');

    expect($membership->fresh()->left_at)->toBeNull();
});

it('does not remove the owner or remove members from an archived group', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $member = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    $ownerMembership = GroupMember::factory()->owner()->for($group)->for($owner)->create();
    $memberMembership = GroupMember::factory()->for($group)->for($member)->create();
    $token = $owner->createToken('Owner phone');

    $this->withToken($token->plainTextToken)
        ->deleteJson("/api/v1/groups/{$group->id}/members/{$ownerMembership->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('member');

    $group->update(['archived_at' => now()]);
    $this->withToken($token->plainTextToken)
        ->deleteJson("/api/v1/groups/{$group->id}/members/{$memberMembership->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('group');
});

it('allows only the group owner and conceals unrelated memberships', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $member = User::factory()->create(['default_currency_code' => $currency->code]);
    $otherOwner = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    $membership = GroupMember::factory()->for($group)->for($member)->create();
    $otherGroup = Group::factory()->for($otherOwner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($otherGroup)->for($otherOwner)->create();
    $otherMembership = GroupMember::factory()->for($otherGroup)->create();

    $memberToken = $member->createToken('Member phone');
    $this->withToken($memberToken->plainTextToken)
        ->deleteJson("/api/v1/groups/{$group->id}/members/{$membership->id}")
        ->assertForbidden();

    app('auth')->forgetGuards();
    $ownerToken = $owner->createToken('Owner phone');
    $this->withToken($ownerToken->plainTextToken)
        ->deleteJson("/api/v1/groups/{$group->id}/members/{$otherMembership->id}")
        ->assertNotFound();
});
