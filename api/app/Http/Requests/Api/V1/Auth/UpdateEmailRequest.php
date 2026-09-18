<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmailRequest extends FormRequest
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
            'current_password' => ['required', 'string'],
            'email' => [
                'required',
                'confirmed',
                'string',
                'email:rfc',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->user()?->id),
            ],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('current_password')
                && ! Hash::check($this->string('current_password')->toString(), $this->user()->password)) {
                $validator->errors()->add('current_password', 'The current password is incorrect.');
            }

            if (! $validator->errors()->has('email') && $this->string('email')->toString() === $this->user()->email) {
                $validator->errors()->add('email', 'The new email must be different from the current email.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => $this->string('email')->trim()->lower()->toString(),
            'email_confirmation' => $this->string('email_confirmation')->trim()->lower()->toString(),
        ]);
    }
}
