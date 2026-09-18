<?php

use App\ExpenseType;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

it('soft deletes an eligible account, revokes tokens, and preserves memberships', function () {
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
});

it('blocks deletion until every owned group is transferred', function () {
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
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('account');

    $this->assertNotSoftDeleted('users', ['id' => $owner->id]);
});

it('blocks deletion while the user has an open group balance', function () {
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
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('account');

    $this->assertNotSoftDeleted('users', ['id' => $user->id]);
});

it('requires the current password', function () {
    $user = User::factory()->create(['password' => 'current-password-value']);
    $token = $user->createToken('User phone');

    $this->withToken($token->plainTextToken)->deleteJson('/api/v1/me', [
        'current_password' => 'incorrect-password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');
});
