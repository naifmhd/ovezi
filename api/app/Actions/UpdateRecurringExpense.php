<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\RecurringExpense;
use App\Models\User;
use App\RecurrenceFrequency;
use App\Services\RecurrenceSchedule;
use App\Services\RecurringExpenseContextValidator;
use App\SplitType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateRecurringExpense
{
    public function __construct(
        private readonly RecurringExpenseContextValidator $contextValidator,
        private readonly RecurrenceSchedule $recurrenceSchedule,
    ) {}

    /**
     * @param array{
     *     payer_user_id?: int|null,
     *     payer_placeholder_id?: int|null,
     *     amount_minor: int,
     *     currency_code: string,
     *     description: string,
     *     category?: string|null,
     *     split_type?: SplitType|null,
     *     participants?: list<array{user_id?: int|null, placeholder_id?: int|null, value?: int}>,
     *     frequency: RecurrenceFrequency,
     *     ends_on?: CarbonImmutable|null
     * } $data
     */
    public function execute(User $editor, RecurringExpense $recurringExpense, array $data): RecurringExpense
    {
        if ($recurringExpense->canceled_at !== null) {
            throw ValidationException::withMessages([
                'recurring_expense' => 'A canceled schedule cannot be edited.',
            ]);
        }

        $payerUserId = $data['payer_user_id'] ?? null;
        $payerPlaceholderId = $data['payer_placeholder_id'] ?? null;
        $participants = $data['participants'] ?? [];
        $this->contextValidator->validate(
            $editor,
            $recurringExpense,
            $payerUserId,
            $payerPlaceholderId,
            $participants,
        );

        $nextOccurrence = $this->recurrenceSchedule->firstAfter(
            CarbonImmutable::instance($recurringExpense->start_on),
            CarbonImmutable::today(),
            $data['frequency'],
        );
        if (($data['ends_on'] ?? null) !== null && $nextOccurrence->greaterThan($data['ends_on'])) {
            $nextOccurrence = null;
        }

        return DB::transaction(function () use (
            $editor,
            $recurringExpense,
            $data,
            $payerUserId,
            $payerPlaceholderId,
            $participants,
            $nextOccurrence,
        ): RecurringExpense {
            $recurringExpense->update([
                'payer_user_id' => $payerUserId,
                'payer_placeholder_id' => $payerPlaceholderId,
                'amount_minor' => $data['amount_minor'],
                'currency_code' => $data['currency_code'],
                'description' => $data['description'],
                'category' => $data['category'] ?? null,
                'split_type' => $data['split_type'] ?? null,
                'frequency' => $data['frequency'],
                'next_occurrence_on' => $nextOccurrence,
                'ends_on' => $data['ends_on'] ?? null,
            ]);
            $recurringExpense->splits()->delete();

            foreach ($participants as $participant) {
                $recurringExpense->splits()->create([
                    'user_id' => $participant['user_id'] ?? null,
                    'placeholder_id' => $participant['placeholder_id'] ?? null,
                    'split_value' => ($data['split_type'] ?? SplitType::Equal) === SplitType::Equal
                        ? null
                        : $participant['value'],
                ]);
            }

            ActivityLog::query()->create([
                'group_id' => $recurringExpense->group_id,
                'actor_id' => $editor->id,
                'subject_type' => $recurringExpense->getMorphClass(),
                'subject_id' => $recurringExpense->id,
                'event' => 'recurring_expense.updated',
            ]);

            return $recurringExpense->load('splits');
        });
    }
}
