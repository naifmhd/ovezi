<?php

use App\Jobs\PurgeDeletedExpenses;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\User;

it('permanently removes only expenses deleted for at least 30 days', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $expenseAttributes = [
        'expense_type' => 'personal',
        'payer_user_id' => $owner->id,
        'created_by' => $owner->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ];
    $expired = Expense::factory()->create($expenseAttributes);
    $recent = Expense::factory()->create($expenseAttributes);
    $active = Expense::factory()->create($expenseAttributes);
    $expired->delete();
    $recent->delete();
    $expired->forceFill(['deleted_at' => now()->subDays(30)])->saveQuietly();
    $recent->forceFill(['deleted_at' => now()->subDays(29)])->saveQuietly();

    (new PurgeDeletedExpenses)->handle();

    expect(Expense::withTrashed()->find($expired->id))->toBeNull()
        ->and(Expense::withTrashed()->find($recent->id))->not->toBeNull()
        ->and(Expense::query()->find($active->id))->not->toBeNull();
});
