<?php

use App\ExpenseType;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\Settlement;
use App\Models\User;
use App\Services\GroupBalanceCalculator;

it('calculates group balances from captured reporting splits and settlements', function () {
    $currency = Currency::factory()->mvr()->create();
    $payer = User::factory()->create(['default_currency_code' => $currency->code]);
    $debtor = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($payer, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    $expense = Expense::factory()->create([
        'expense_type' => ExpenseType::Group,
        'group_id' => $group->id,
        'payer_user_id' => $payer->id,
        'amount_minor' => 1000,
        'currency_code' => $currency->code,
        'reporting_amount_minor' => 1000,
        'reporting_currency_code' => $currency->code,
        'created_by' => $payer->id,
    ]);
    ExpenseSplit::factory()->for($expense)->for($payer)->create([
        'amount_owed_minor' => 400,
        'reporting_amount_owed_minor' => 400,
    ]);
    ExpenseSplit::factory()->for($expense)->for($debtor)->create([
        'amount_owed_minor' => 600,
        'reporting_amount_owed_minor' => 600,
    ]);
    Settlement::factory()->create([
        'group_id' => $group->id,
        'from_user_id' => $debtor->id,
        'to_user_id' => $payer->id,
        'amount_minor' => 250,
        'currency_code' => $currency->code,
        'reporting_amount_minor' => 250,
        'reporting_currency_code' => $currency->code,
        'created_by' => $debtor->id,
    ]);

    $balances = app(GroupBalanceCalculator::class)->calculate($group);

    expect($balances)->toBe([
        "user:{$payer->id}" => 350,
        "user:{$debtor->id}" => -350,
    ])->and(array_sum($balances))->toBe(0);
});
