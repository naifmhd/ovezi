<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Expense;
use Illuminate\Validation\Validator;

class UpdateExpenseRequest extends StoreExpenseRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            && ($this->user()?->can('update', $expense) ?? false);
    }

    public function rules(): array
    {
        return [
            ...parent::rules(),
            'recalculate_rate' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                $expense = $this->route('expense');

                if (! $expense instanceof Expense) {
                    return;
                }

                if (($expense->splits()->where('amount_paid_minor', '>', 0)->count() > 1 || $expense->splits()->where('included_in_split', false)->exists())
                    && ! collect($this->input('participants', []))->contains(fn ($participant): bool => is_array($participant) && isset($participant['amount_paid_minor']))) {
                    $validator->errors()->add('participants', 'Update Ovezi to edit this expense’s payments and shares.');
                }

                if ($this->input('expense_type') !== $expense->expense_type->value) {
                    $validator->errors()->add('expense_type', 'An expense cannot be moved to another type.');
                }

                $requestedGroupId = $this->filled('group_id') ? $this->integer('group_id') : null;

                if ($requestedGroupId !== $expense->group_id) {
                    $validator->errors()->add('group_id', 'An expense cannot be moved to another group.');
                }
            },
        ];
    }
}
