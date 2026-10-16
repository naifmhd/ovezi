<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLendingRepaymentRequest extends FormRequest
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
            'currency_code' => ['required', 'exists:currencies,code'],
            'occurred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
            'loan_id' => ['nullable', 'integer'],
        ];
    }
}
