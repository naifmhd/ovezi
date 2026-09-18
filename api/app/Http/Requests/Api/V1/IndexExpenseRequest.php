<?php

namespace App\Http\Requests\Api\V1;

use App\ExpenseType;
use App\Models\Expense;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Expense::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'group_id' => ['sometimes', 'integer', 'exists:groups,id'],
            'expense_type' => ['sometimes', Rule::enum(ExpenseType::class)],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('expense_type')) {
            $this->merge([
                'expense_type' => $this->string('expense_type')->trim()->lower()->toString(),
            ]);
        }
    }
}
