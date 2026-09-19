<?php

use App\Models\Currency;
use App\Models\Expense;
use App\Models\RecurringExpense;
use App\Models\User;
use Illuminate\Support\Carbon;

it('requires authentication', function () {
    $this->getJson('/api/v1/recurring-expenses')->assertUnauthorized();
    $this->postJson('/api/v1/recurring-expenses')->assertUnauthorized();
});

it('creates the first expense immediately and schedules separate future occurrences', function () {
    $currency = Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => $currency->code]);
    $token = $user->createToken('Phone')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/recurring-expenses', [
        'expense_type' => 'personal',
        'payer_user_id' => $user->id,
        'amount_minor' => 4500,
        'currency_code' => $currency->code,
        'description' => 'Monthly internet',
        'category' => 'home',
        'occurred_at' => '2026-01-31T12:00:00Z',
        'frequency' => 'monthly',
        'ends_on' => '2026-04-30',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.expense.description', 'Monthly internet')
        ->assertJsonPath('data.recurring_expense.frequency', 'monthly')
        ->assertJsonPath('data.recurring_expense.status', 'active')
        ->assertJsonPath('data.recurring_expense.can_manage', true);

    $recurringExpense = RecurringExpense::query()->sole();
    $expense = Expense::query()->sole();
    expect($recurringExpense->start_on->toDateString())->toBe('2026-01-31')
        ->and($recurringExpense->next_occurrence_on->toDateString())->toBe('2026-02-28')
        ->and($expense->recurring_expense_id)->toBe($recurringExpense->id)
        ->and($expense->recurring_occurrence_on->toDateString())->toBe('2026-01-31');
});

it('validates recurrence settings', function () {
    $currency = Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => $currency->code]);

    $this->actingAs($user)->postJson('/api/v1/recurring-expenses', [
        'expense_type' => 'personal',
        'payer_user_id' => $user->id,
        'amount_minor' => 4500,
        'currency_code' => $currency->code,
        'description' => 'Internet',
        'occurred_at' => '2026-01-31T12:00:00Z',
        'frequency' => 'daily',
        'ends_on' => '2026-01-30',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['frequency', 'ends_on']);
});

it('lists only visible schedules and lets a manager pause resume and cancel', function () {
    Carbon::setTestNow('2026-02-10 09:00:00');
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
    $schedule = RecurringExpense::factory()->for($owner, 'creator')->create([
        'payer_user_id' => $owner->id,
        'currency_code' => $currency->code,
        'start_on' => '2026-01-31',
        'next_occurrence_on' => '2026-02-28',
    ]);

    $this->actingAs($owner)
        ->getJson('/api/v1/recurring-expenses')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $schedule->id);
    $this->actingAs($outsider)
        ->getJson("/api/v1/recurring-expenses/{$schedule->id}")
        ->assertForbidden();

    $this->actingAs($owner)
        ->putJson("/api/v1/recurring-expenses/{$schedule->id}/pause")
        ->assertOk()
        ->assertJsonPath('data.status', 'paused');
    $this->actingAs($owner)
        ->deleteJson("/api/v1/recurring-expenses/{$schedule->id}/pause")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
    expect($schedule->fresh()->next_occurrence_on->toDateString())->toBe('2026-02-28');

    $this->actingAs($owner)
        ->deleteJson("/api/v1/recurring-expenses/{$schedule->id}")
        ->assertNoContent();
    $canceledSchedule = $schedule->fresh();
    expect($canceledSchedule->status())->toBe('canceled')
        ->and($canceledSchedule->next_occurrence_on)->toBeNull();

    Carbon::setTestNow();
});

it('updates future occurrences without changing an already generated expense', function () {
    Carbon::setTestNow('2026-02-10 09:00:00');
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $schedule = RecurringExpense::factory()->for($owner, 'creator')->create([
        'payer_user_id' => $owner->id,
        'currency_code' => $currency->code,
        'description' => 'Old description',
        'start_on' => '2026-01-31',
        'next_occurrence_on' => '2026-02-28',
    ]);
    $existingExpense = Expense::factory()->create([
        'recurring_expense_id' => $schedule->id,
        'recurring_occurrence_on' => '2026-01-31',
        'expense_type' => 'personal',
        'payer_user_id' => $owner->id,
        'created_by' => $owner->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
        'description' => 'Old description',
    ]);

    $this->actingAs($owner)->patchJson("/api/v1/recurring-expenses/{$schedule->id}", [
        'expense_type' => 'personal',
        'payer_user_id' => $owner->id,
        'amount_minor' => 9900,
        'currency_code' => $currency->code,
        'description' => 'New description',
        'occurred_at' => '2026-01-31T12:00:00Z',
        'frequency' => 'yearly',
        'ends_on' => '2028-12-31',
    ])->assertOk()
        ->assertJsonPath('data.description', 'New description')
        ->assertJsonPath('data.frequency', 'yearly');

    expect($schedule->fresh()->amount_minor)->toBe(9900)
        ->and($schedule->fresh()->next_occurrence_on->toDateString())->toBe('2027-01-31')
        ->and($existingExpense->fresh()->description)->toBe('Old description')
        ->and($existingExpense->amount_minor)->not->toBe(9900);
    Carbon::setTestNow();
});
