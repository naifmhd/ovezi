<?php

use App\ExpenseType;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

it('returns member balances and deterministic settlement suggestions in the group currency', function () {
    $currency = Currency::factory()->mvr()->create();
    $payer = User::factory()->create(['default_currency_code' => $currency->code]);
    $debtor = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($payer, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($payer)->create();
    GroupMember::factory()->for($group)->for($debtor)->create();
    $expense = Expense::factory()->create([
        'expense_type' => ExpenseType::Group,
        'group_id' => $group->id,
        'payer_user_id' => $payer->id,
        'amount_minor' => 1001,
        'currency_code' => $currency->code,
        'reporting_amount_minor' => 1001,
        'reporting_currency_code' => $currency->code,
        'created_by' => $payer->id,
    ]);
    ExpenseSplit::factory()->for($expense)->for($payer)->create([
        'amount_owed_minor' => 501,
        'reporting_amount_owed_minor' => 501,
    ]);
    ExpenseSplit::factory()->for($expense)->for($debtor)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);
    $token = $debtor->createToken('Member phone');

    $response = $this->withToken($token->plainTextToken)
        ->getJson("/api/v1/groups/{$group->id}/balances");

    $response
        ->assertOk()
        ->assertJsonPath('data.currency_code', $currency->code)
        ->assertJsonPath('data.members.0.balance_minor', 500)
        ->assertJsonPath('data.members.1.balance_minor', -500)
        ->assertJsonPath('data.suggested_settlements.0.from', "user:{$debtor->id}")
        ->assertJsonPath('data.suggested_settlements.0.to', "user:{$payer->id}")
        ->assertJsonPath('data.suggested_settlements.0.amount_minor', 500);
});

it('hides group balances from outsiders', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    $token = $outsider->createToken('Outsider phone');

    $this->withToken($token->plainTextToken)
        ->getJson("/api/v1/groups/{$group->id}/balances")
        ->assertNotFound();
});
