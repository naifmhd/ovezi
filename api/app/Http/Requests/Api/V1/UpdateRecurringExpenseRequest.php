<?php

namespace App\Http\Requests\Api\V1;

use App\Models\RecurringExpense;
use Illuminate\Validation\Validator;

class UpdateRecurringExpenseRequest extends StoreRecurringExpenseRequest
{
    public function authorize(): bool
    {
        $recurringExpense = $this->route('recurringExpense');

        return $recurringExpense instanceof RecurringExpense
            && ($this->user()?->can('update', $recurringExpense) ?? false);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'expense_rate' => ['prohibited'],
            'confirmed_duplicate' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $recurringExpense = $this->route('recurringExpense');

                if (! $recurringExpense instanceof RecurringExpense) {
                    return;
                }

                if ($this->input('expense_type') !== $recurringExpense->expense_type->value) {
                    $validator->errors()->add('expense_type', 'A recurring expense cannot be moved to another type.');
                }

                $requestedGroupId = $this->filled('group_id') ? $this->integer('group_id') : null;
                if ($requestedGroupId !== $recurringExpense->group_id) {
                    $validator->errors()->add('group_id', 'A recurring expense cannot be moved to another group.');
                }

                if ($this->date('occurred_at')?->toDateString() !== $recurringExpense->start_on->toDateString()) {
                    $validator->errors()->add('occurred_at', 'The first occurrence date cannot be changed.');
                }
            },
        ];
    }
}
