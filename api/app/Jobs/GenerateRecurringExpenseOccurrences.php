<?php

namespace App\Jobs;

use App\Actions\CreateExpense;
use App\Exceptions\InvalidSplit;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\RecurringExpense;
use App\Services\RecurrenceSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerateRecurringExpenseOccurrences implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $recurringExpenseId) {}

    public function uniqueId(): string
    {
        return (string) $this->recurringExpenseId;
    }

    public function handle(CreateExpense $createExpense, RecurrenceSchedule $recurrenceSchedule): void
    {
        for ($generated = 0; $generated < 100; $generated++) {
            try {
                $hasAnotherDueOccurrence = DB::transaction(function () use (
                    $createExpense,
                    $recurrenceSchedule,
                ): bool {
                    $recurringExpense = RecurringExpense::query()
                        ->with(['creator', 'group', 'splits'])
                        ->lockForUpdate()
                        ->find($this->recurringExpenseId);

                    if ($recurringExpense === null
                        || $recurringExpense->paused_at !== null
                        || $recurringExpense->canceled_at !== null
                        || $recurringExpense->next_occurrence_on === null
                        || $recurringExpense->next_occurrence_on->isAfter(today())) {
                        return false;
                    }

                    $occurrenceOn = CarbonImmutable::instance($recurringExpense->next_occurrence_on);
                    $expense = Expense::query()
                        ->whereBelongsTo($recurringExpense)
                        ->whereDate('recurring_occurrence_on', $occurrenceOn)
                        ->first();

                    if ($expense === null) {
                        if ($recurringExpense->creator === null) {
                            $this->pauseInvalidSchedule($recurringExpense, 'The schedule creator no longer has an active account.');

                            return false;
                        }

                        $expense = $createExpense->execute($recurringExpense->creator, [
                            'expense_type' => $recurringExpense->expense_type,
                            'group' => $recurringExpense->group,
                            'payer_user_id' => $recurringExpense->payer_user_id,
                            'payer_placeholder_id' => $recurringExpense->payer_placeholder_id,
                            'amount_minor' => $recurringExpense->amount_minor,
                            'currency_code' => $recurringExpense->currency_code,
                            'description' => $recurringExpense->description,
                            'category' => $recurringExpense->category,
                            'occurred_at' => $occurrenceOn->setTime(12, 0),
                            'split_type' => $recurringExpense->split_type,
                            'participants' => $recurringExpense->splits->map(fn ($split): array => [
                                'user_id' => $split->user_id,
                                'placeholder_id' => $split->placeholder_id,
                                ...($split->split_value === null ? [] : ['value' => (int) $split->split_value]),
                            ])->all(),
                        ]);
                        $expense->update([
                            'recurring_expense_id' => $recurringExpense->id,
                            'recurring_occurrence_on' => $occurrenceOn,
                        ]);
                    }

                    $nextOccurrence = $recurrenceSchedule->next(
                        CarbonImmutable::instance($recurringExpense->start_on),
                        $occurrenceOn,
                        $recurringExpense->frequency,
                    );
                    if ($recurringExpense->ends_on !== null && $nextOccurrence->greaterThan($recurringExpense->ends_on)) {
                        $nextOccurrence = null;
                    }

                    $recurringExpense->update(['next_occurrence_on' => $nextOccurrence]);

                    return $nextOccurrence !== null && $nextOccurrence->lessThanOrEqualTo(today());
                });
            } catch (InvalidSplit $exception) {
                $this->pauseAfterValidationFailure($exception->getMessage());

                return;
            } catch (ValidationException $exception) {
                if (! array_intersect(array_keys($exception->errors()), [
                    'expense_type', 'group', 'participants', 'payer_user_id', 'payer_placeholder_id',
                ])) {
                    throw $exception;
                }

                $this->pauseAfterValidationFailure(
                    (string) collect($exception->errors())->flatten()->first(),
                );

                return;
            }

            if (! $hasAnotherDueOccurrence) {
                return;
            }
        }
    }

    private function pauseAfterValidationFailure(string $reason): void
    {
        DB::transaction(function () use ($reason): void {
            $recurringExpense = RecurringExpense::query()->lockForUpdate()->find($this->recurringExpenseId);

            if ($recurringExpense !== null && $recurringExpense->canceled_at === null) {
                $this->pauseInvalidSchedule($recurringExpense, $reason);
            }
        });
    }

    private function pauseInvalidSchedule(RecurringExpense $recurringExpense, string $reason): void
    {
        $recurringExpense->update(['paused_at' => now()]);
        ActivityLog::query()->create([
            'group_id' => $recurringExpense->group_id,
            'actor_id' => $recurringExpense->created_by,
            'subject_type' => $recurringExpense->getMorphClass(),
            'subject_id' => $recurringExpense->id,
            'event' => 'recurring_expense.paused',
            'metadata' => ['reason' => $reason],
        ]);
    }
}
