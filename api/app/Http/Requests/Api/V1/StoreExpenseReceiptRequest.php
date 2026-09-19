<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Expense;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense instanceof Expense
            && ($this->user()?->can('update', $expense) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'receipt' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,heic,heif',
                'max:10240',
            ],
        ];
    }
}
