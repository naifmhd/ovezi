<?php

use App\ExchangeRateSource;
use App\Jobs\GenerateDueRecurringExpenses;
use App\Jobs\GenerateRecurringExpenseOccurrences;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\RecurringExpense;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

it('dispatches one generation job for each due schedule', function () {
    Carbon::setTestNow('2026-03-31 19:00:00');
    Queue::fake();
    $currency = Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => $currency->code]);
    $due = RecurringExpense::factory()->for($user, 'creator')->create([
        'payer_user_id' => $user->id,
        'currency_code' => $currency->code,
        'next_occurrence_on' => today(),
    ]);
    RecurringExpense::factory()->for($user, 'creator')->create([
        'payer_user_id' => $user->id,
        'currency_code' => $currency->code,
        'next_occurrence_on' => today()->addDay(),
    ]);

    (new GenerateDueRecurringExpenses)->handle();

    Queue::assertPushed(
        GenerateRecurringExpenseOccurrences::class,
        fn ($job): bool => $job->recurringExpenseId === $due->id,
    );
    Queue::assertPushed(GenerateRecurringExpenseOccurrences::class, 1);
    Carbon::setTestNow();
});

it('generates due expenses once and captures the rate for each occurrence date', function () {
    Carbon::setTestNow('2026-03-31 19:00:00');
    $mvr = Currency::factory()->mvr()->create();
    $usd = Currency::factory()->usd()->create();
    $user = User::factory()->create(['default_currency_code' => $mvr->code]);
    ExchangeRate::factory()->create([
        'base_currency_code' => $usd->code,
        'quote_currency_code' => $mvr->code,
        'rate' => '15.420000000000',
        'effective_date' => '2026-03-31',
        'fetched_at' => now(),
    ]);
    $schedule = RecurringExpense::factory()->for($user, 'creator')->create([
        'payer_user_id' => $user->id,
        'amount_minor' => 1000,
        'currency_code' => $usd->code,
        'start_on' => '2026-01-31',
        'next_occurrence_on' => '2026-03-31',
        'frequency' => 'monthly',
    ]);

    $job = new GenerateRecurringExpenseOccurrences($schedule->id);
    app()->call([$job, 'handle']);
    app()->call([$job, 'handle']);

    expect(Expense::query()->count())->toBe(1);
    $expense = Expense::query()->sole();
    expect($expense->recurring_expense_id)->toBe($schedule->id)
        ->and($expense->recurring_occurrence_on->toDateString())->toBe('2026-03-31')
        ->and($expense->exchange_rate_source)->toBe(ExchangeRateSource::Provider)
        ->and($expense->exchange_rate)->toBe('15.420000000000')
        ->and($schedule->fresh()->next_occurrence_on->toDateString())->toBe('2026-04-30');

    Carbon::setTestNow();
});
