<?php

use App\ExchangeRateSource;
use App\ExpenseType;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\Placeholder;
use App\Models\User;

it('stores integer amounts and a fixed precision rate snapshot', function () {
    Currency::factory()->mvr()->create();
    Currency::factory()->usd()->create();
    $expense = Expense::factory()->create([
        'currency_code' => 'USD',
        'reporting_currency_code' => 'MVR',
        'amount_minor' => 1000,
        'reporting_amount_minor' => 15420,
        'exchange_rate' => '15.420000000000',
        'exchange_rate_source' => ExchangeRateSource::Provider,
        'exchange_rate_effective_date' => '2026-09-18',
    ]);

    expect($expense->amount_minor)->toBe(1000);
    expect($expense->reporting_amount_minor)->toBe(15420);
    expect($expense->exchange_rate)->toBe('15.420000000000');
    expect($expense->exchange_rate_source)->toBe(ExchangeRateSource::Provider);
    expect($expense->exchange_rate_effective_date->toDateString())->toBe('2026-09-18');
});

it('rejects multiple payer references', function () {
    $user = User::factory()->create();
    $placeholder = Placeholder::factory()->create();

    expect(fn () => Expense::factory()->create([
        'payer_user_id' => $user->id,
        'payer_placeholder_id' => $placeholder->id,
    ]))->toThrow(InvalidArgumentException::class, 'exactly one payer');
});

it('rejects group expenses without a group', function () {
    expect(fn () => Expense::factory()->create([
        'expense_type' => ExpenseType::Group,
        'group_id' => null,
    ]))->toThrow(InvalidArgumentException::class, 'Only group expenses may reference a group');
});
