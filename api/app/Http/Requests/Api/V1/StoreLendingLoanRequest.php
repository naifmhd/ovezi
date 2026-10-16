<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLendingLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['currency_code' => strtoupper((string) $this->input('currency_code'))]);
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['lent', 'borrowed'])],
            'amount_minor' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'currency_code' => ['required', Rule::exists('currencies', 'code')->where('is_active', true)],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'due_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:occurred_on'],
            'description' => ['nullable', 'string', 'max:500'],
            'private_note' => ['nullable', 'string', 'max:2000'],
            'shared' => ['sometimes', 'boolean'],
        ];
    }
}
