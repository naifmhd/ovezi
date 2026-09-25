<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDirectSettlementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'from_placeholder_id' => ['nullable', 'integer', 'exists:placeholders,id'],
            'to_user_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'to_placeholder_id' => ['nullable', 'integer', 'exists:placeholders,id'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'reporting_currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'method' => ['nullable', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
            'occurred_at' => ['required', 'date'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny([
                'from_user_id', 'from_placeholder_id', 'to_user_id', 'to_placeholder_id',
            ])) {
                return;
            }

            $from = $this->participantKey($this->input('from_user_id'), $this->input('from_placeholder_id'));
            $to = $this->participantKey($this->input('to_user_id'), $this->input('to_placeholder_id'));

            if ($from === null) {
                $validator->errors()->add('from_user_id', 'Select exactly one settlement sender.');
            }

            if ($to === null) {
                $validator->errors()->add('to_user_id', 'Select exactly one settlement recipient.');
            }

            if ($from !== null && $from === $to) {
                $validator->errors()->add('to_user_id', 'The settlement recipient must be different from the sender.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'currency_code' => $this->string('currency_code')->trim()->upper()->toString(),
            'reporting_currency_code' => $this->string('reporting_currency_code')->trim()->upper()->toString(),
            'method' => $this->filled('method') ? $this->string('method')->trim()->lower()->toString() : null,
            'note' => $this->filled('note') ? $this->string('note')->trim()->toString() : null,
        ]);
    }

    private function participantKey(mixed $userId, mixed $placeholderId): ?string
    {
        if (($userId === null) === ($placeholderId === null)) {
            return null;
        }

        return $userId !== null ? "user:{$userId}" : "placeholder:{$placeholderId}";
    }
}
