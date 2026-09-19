<?php

namespace App\Actions;

use App\ExpenseType;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\Group;
use App\Models\RecurringExpense;
use App\Models\User;
use App\RecurrenceFrequency;
use App\Services\RecurrenceSchedule;
use App\SplitType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateRecurringExpense
{
    public function __construct(
        private readonly CreateExpense $createExpense,
        private readonly RecurrenceSchedule $recurrenceSchedule,
    ) {}

    /**
     * @param array{
     *     expense_type: ExpenseType,
     *     group?: Group|null,
     *     payer_user_id?: int|null,
     *     payer_placeholder_id?: int|null,
     *     amount_minor: int,
     *     currency_code: string,
     *     description: string,
     *     category?: string|null,
     *     occurred_at: CarbonImmutable,
     *     split_type?: SplitType,
     *     participants?: list<array{user_id?: int|null, placeholder_id?: int|null, value?: int}>,
     *     expense_rate?: string|null
     * } $expenseData
     * @return array{expense: Expense, recurring_expense: RecurringExpense}
     */
    public function execute(
        User $creator,
        array $expenseData,
        RecurrenceFrequency $frequency,
        ?CarbonImmutable $endsOn,
    ): array {
        return DB::transaction(function () use ($creator, $expenseData, $frequency, $endsOn): array {
            $startOn = $expenseData['occurred_at']->startOfDay();
            $nextOccurrence = $this->recurrenceSchedule->next($startOn, $startOn, $frequency);

            if ($endsOn !== null && $nextOccurrence->greaterThan($endsOn)) {
                $nextOccurrence = null;
            }

            $recurringExpense = RecurringExpense::query()->create([
                'expense_type' => $expenseData['expense_type'],
                'group_id' => $expenseData['group']?->id,
                'payer_user_id' => $expenseData['payer_user_id'] ?? null,
                'payer_placeholder_id' => $expenseData['payer_placeholder_id'] ?? null,
                'amount_minor' => $expenseData['amount_minor'],
                'currency_code' => $expenseData['currency_code'],
                'description' => $expenseData['description'],
                'category' => $expenseData['category'] ?? null,
                'split_type' => $expenseData['expense_type'] === ExpenseType::Personal
                    ? null
                    : ($expenseData['split_type'] ?? SplitType::Equal),
                'frequency' => $frequency,
                'start_on' => $startOn,
                'next_occurrence_on' => $nextOccurrence,
                'ends_on' => $endsOn,
                'created_by' => $creator->id,
            ]);

            foreach ($expenseData['participants'] ?? [] as $participant) {
                $recurringExpense->splits()->create([
                    'user_id' => $participant['user_id'] ?? null,
                    'placeholder_id' => $participant['placeholder_id'] ?? null,
                    'split_value' => ($expenseData['split_type'] ?? SplitType::Equal) === SplitType::Equal
                        ? null
                        : $participant['value'],
                ]);
            }

            $expense = $this->createExpense->execute($creator, $expenseData);
            $expense->update([
                'recurring_expense_id' => $recurringExpense->id,
                'recurring_occurrence_on' => $startOn,
            ]);

            ActivityLog::query()->create([
                'group_id' => $recurringExpense->group_id,
                'actor_id' => $creator->id,
                'subject_type' => $recurringExpense->getMorphClass(),
                'subject_id' => $recurringExpense->id,
                'event' => 'recurring_expense.created',
                'metadata' => ['frequency' => $frequency->value],
            ]);

            return [
                'expense' => $expense->refresh()->load('splits'),
                'recurring_expense' => $recurringExpense->load('splits'),
            ];
        });
    }
}
