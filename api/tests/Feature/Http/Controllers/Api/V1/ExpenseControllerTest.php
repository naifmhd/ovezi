<?php

use App\Models\Currency;
use App\Models\Expense;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Placeholder;
use App\Models\User;

it('returns 401 when no access token is provided', function () {
    $this->postJson('/api/v1/expenses')->assertUnauthorized();
    $this->getJson('/api/v1/expenses')->assertUnauthorized();
});

it('lists only expenses visible to the authenticated user', function () {
    $currency = Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => $currency->code]);
    $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($user, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($user)->create();
    $visibleGroupExpense = Expense::factory()->for($group)->create([
        'expense_type' => 'group',
        'payer_user_id' => $user->id,
        'created_by' => $user->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);
    $visiblePersonalExpense = Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $user->id,
        'created_by' => $user->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);
    Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $outsider->id,
        'created_by' => $outsider->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);
    $token = $user->createToken('User phone');

    $response = $this->withToken($token->plainTextToken)->getJson('/api/v1/expenses?per_page=100');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toContain($visibleGroupExpense->id, $visiblePersonalExpense->id);
});

it('filters expenses by group and returns calculated splits', function () {
    $currency = Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($user, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($user)->create();
    $token = $user->createToken('User phone');

    $this->withToken($token->plainTextToken)->postJson('/api/v1/expenses', [
        'expense_type' => 'group',
        'group_id' => $group->id,
        'payer_user_id' => $user->id,
        'amount_minor' => 1200,
        'currency_code' => $currency->code,
        'description' => 'Lunch',
        'occurred_at' => '2026-09-18T09:00:00+05:00',
        'split_type' => 'equal',
        'participants' => [['user_id' => $user->id]],
    ])->assertCreated();

    $response = $this->withToken($token->plainTextToken)
        ->getJson("/api/v1/expenses?group_id={$group->id}");

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.description', 'Lunch')
        ->assertJsonPath('data.0.splits.0.amount_owed_minor', 1200);
});

it('shows an expense visible to the authenticated user', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $expense = Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $owner->id,
        'created_by' => $owner->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);

    $this->withToken($owner->createToken('Owner phone')->plainTextToken)
        ->getJson("/api/v1/expenses/{$expense->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $expense->id);
});

it('forbids viewing an expense belonging to someone else', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
    $expense = Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $owner->id,
        'created_by' => $owner->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);

    $this->withToken($outsider->createToken('Outsider phone')->plainTextToken)
        ->getJson("/api/v1/expenses/{$expense->id}")
        ->assertForbidden();
});

it('returns 403 when creating an expense in another group', function () {
    $currency = Currency::factory()->mvr()->create();
    $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    $token = $outsider->createToken('Outsider phone');

    $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/expenses', [
        'expense_type' => 'group',
        'group_id' => $group->id,
        'payer_user_id' => $outsider->id,
        'amount_minor' => 1000,
        'currency_code' => $currency->code,
        'description' => 'Private group expense',
        'occurred_at' => '2026-09-18T09:00:00+05:00',
        'split_type' => 'equal',
        'participants' => [
            ['user_id' => $outsider->id],
        ],
    ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('expenses', 0);
});

it('returns 422 with clear messages for invalid expense data', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test phone');

    $response = $this
        ->withToken($token->plainTextToken)
        ->postJson('/api/v1/expenses');

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['expense_type', 'amount_minor', 'currency_code', 'description', 'occurred_at'])
        ->assertJsonPath('errors.expense_type.0', 'The expense type field is required.');
    $this->assertDatabaseCount('expenses', 0);
});

it('returns 422 when exact splits do not equal the expense amount', function () {
    $currency = Currency::factory()->mvr()->create();
    $creator = User::factory()->create(['default_currency_code' => $currency->code]);
    $otherMember = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($creator, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($creator)->create();
    GroupMember::factory()->for($group)->for($otherMember)->create();
    $token = $creator->createToken('Creator phone');

    $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/expenses', [
        'expense_type' => 'group',
        'group_id' => $group->id,
        'payer_user_id' => $creator->id,
        'amount_minor' => 1000,
        'currency_code' => $currency->code,
        'description' => 'Dinner',
        'occurred_at' => '2026-09-18T09:00:00+05:00',
        'split_type' => 'exact',
        'participants' => [
            ['user_id' => $creator->id, 'value' => 600],
            ['user_id' => $otherMember->id, 'value' => 300],
        ],
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('participants')
        ->assertJsonPath('errors.participants.0', 'Exact split amounts must equal the expense amount.');
    $this->assertDatabaseCount('expenses', 0);
});

it('creates a group expense and returns its calculated splits', function () {
    $currency = Currency::factory()->mvr()->create();
    $creator = User::factory()->create(['default_currency_code' => $currency->code]);
    $secondMember = User::factory()->create(['default_currency_code' => $currency->code]);
    $thirdMember = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($creator, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($creator)->create();
    GroupMember::factory()->for($group)->for($secondMember)->create();
    GroupMember::factory()->for($group)->for($thirdMember)->create();
    $token = $creator->createToken('Creator phone');

    $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/expenses', [
        'expense_type' => ' GROUP ',
        'group_id' => $group->id,
        'payer_user_id' => $creator->id,
        'amount_minor' => 100,
        'currency_code' => 'mvr',
        'description' => ' Coffee ',
        'category' => ' FOOD ',
        'occurred_at' => '2026-09-18T09:00:00+05:00',
        'split_type' => 'EQUAL',
        'participants' => [
            ['user_id' => $creator->id],
            ['user_id' => $secondMember->id],
            ['user_id' => $thirdMember->id],
        ],
        'created_by' => $thirdMember->id,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.expense_type', 'group')
        ->assertJsonPath('data.description', 'Coffee')
        ->assertJsonPath('data.category', 'food')
        ->assertJsonPath('data.created_by', $creator->id)
        ->assertJsonPath('data.splits.0.amount_owed_minor', 34)
        ->assertJsonPath('data.splits.0.reporting_amount_owed_minor', 34)
        ->assertJsonCount(3, 'data.splits');

    $this->assertDatabaseHas('expenses', [
        'group_id' => $group->id,
        'created_by' => $creator->id,
        'amount_minor' => 100,
        'description' => 'Coffee',
    ]);
    $this->assertDatabaseCount('expense_splits', 3);
    $this->assertDatabaseCount('activity_logs', 1);
});

it('creates a personal tracking expense without splits', function () {
    $currency = Currency::factory()->mvr()->create();
    $creator = User::factory()->create(['default_currency_code' => $currency->code]);
    $token = $creator->createToken('Creator phone');

    $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/expenses', [
        'expense_type' => 'personal',
        'payer_user_id' => $creator->id,
        'amount_minor' => 2500,
        'currency_code' => $currency->code,
        'description' => 'Groceries',
        'occurred_at' => '2026-09-18T09:00:00+05:00',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.expense_type', 'personal')
        ->assertJsonCount(0, 'data.splits');
    $this->assertDatabaseCount('expense_splits', 0);
});

it('creates a direct expense with a creator-owned placeholder', function () {
    $currency = Currency::factory()->mvr()->create();
    $creator = User::factory()->create(['default_currency_code' => $currency->code]);
    $placeholder = Placeholder::factory()->for($creator, 'creator')->create();
    $token = $creator->createToken('Creator phone');

    $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/expenses', [
        'expense_type' => 'direct',
        'payer_user_id' => $creator->id,
        'amount_minor' => 1500,
        'currency_code' => $currency->code,
        'description' => 'Coffee',
        'occurred_at' => '2026-09-18T09:00:00+05:00',
        'split_type' => 'equal',
        'participants' => [
            ['user_id' => $creator->id],
            ['placeholder_id' => $placeholder->id],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.expense_type', 'direct')
        ->assertJsonPath('data.payer.name', $creator->name)
        ->assertJsonPath('data.splits.1.placeholder_id', $placeholder->id)
        ->assertJsonPath('data.splits.1.name', $placeholder->name)
        ->assertJsonPath('data.splits.1.amount_owed_minor', 750);
    $this->assertDatabaseHas('expense_splits', [
        'placeholder_id' => $placeholder->id,
        'amount_owed_minor' => 750,
    ]);
});
