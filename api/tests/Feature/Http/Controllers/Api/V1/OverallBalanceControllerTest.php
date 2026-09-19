<?php

use App\ExpenseType;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

it('returns group and direct balances with totals kept in separate currency buckets', function () {
    $mvr = Currency::factory()->mvr()->create();
    $usd = Currency::factory()->usd()->create();
    $user = User::factory()->create(['default_currency_code' => $mvr->code]);
    $friend = User::factory()->create(['default_currency_code' => $mvr->code]);
    $group = Group::factory()->for($user, 'creator')->create([
        'name' => 'Trip',
        'reporting_currency_code' => $usd->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($user)->create();
    GroupMember::factory()->for($group)->for($friend)->create();
    $groupExpense = Expense::factory()->for($group)->create([
        'expense_type' => ExpenseType::Group,
        'payer_user_id' => $user->id,
        'amount_minor' => 1000,
        'currency_code' => $usd->code,
        'reporting_amount_minor' => 1000,
        'reporting_currency_code' => $usd->code,
        'created_by' => $user->id,
    ]);
    ExpenseSplit::factory()->for($groupExpense)->for($user)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);
    ExpenseSplit::factory()->for($groupExpense)->for($friend)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);
    $directExpense = Expense::factory()->create([
        'expense_type' => ExpenseType::Direct,
        'payer_user_id' => $user->id,
        'amount_minor' => 400,
        'currency_code' => $mvr->code,
        'reporting_amount_minor' => 400,
        'reporting_currency_code' => $mvr->code,
        'created_by' => $user->id,
    ]);
    ExpenseSplit::factory()->for($directExpense)->for($user)->create([
        'amount_owed_minor' => 200,
        'reporting_amount_owed_minor' => 200,
    ]);
    ExpenseSplit::factory()->for($directExpense)->for($friend)->create([
        'amount_owed_minor' => 200,
        'reporting_amount_owed_minor' => 200,
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/balances');

    $response
        ->assertOk()
        ->assertJsonPath('data.groups.0.group_id', $group->id)
        ->assertJsonPath('data.groups.0.balance_minor', 500)
        ->assertJsonPath('data.direct.0.participant.user_id', $friend->id)
        ->assertJsonPath('data.direct.0.balance_minor', 200)
        ->assertJsonFragment(['currency_code' => 'MVR', 'balance_minor' => 200])
        ->assertJsonFragment(['currency_code' => 'USD', 'balance_minor' => 500]);
});

it('requires authentication', function () {
    $this->getJson('/api/v1/balances')->assertUnauthorized();
});
