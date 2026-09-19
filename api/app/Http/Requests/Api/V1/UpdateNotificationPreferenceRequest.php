<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateNotificationPreferenceRequest extends FormRequest
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
            'expense_created' => ['sometimes', 'required', 'boolean'],
            'payment_received' => ['sometimes', 'required', 'boolean'],
            'settle_up_reminders' => ['sometimes', 'required', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->hasAny(['expense_created', 'payment_received', 'settle_up_reminders'])) {
                $validator->errors()->add('preferences', 'Provide at least one notification preference to update.');
            }
        }];
    }
}
