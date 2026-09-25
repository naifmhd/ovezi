<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\ConnectedAccountProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocialLoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(ConnectedAccountProvider::class)],
            'id_token' => ['required', 'string', 'max:16384'],
            'nonce' => [
                Rule::requiredIf($this->input('provider') === ConnectedAccountProvider::Apple->value),
                'nullable',
                'string',
                'between:16,255',
            ],
            'authorization_code' => [Rule::requiredIf(app()->isProduction() && $this->input('provider') === 'apple'), 'nullable', 'string', 'max:4096'],
            'name' => ['nullable', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => $this->string('provider')->trim()->lower()->toString(),
            'name' => $this->filled('name') ? $this->string('name')->trim()->toString() : null,
            'device_name' => $this->string('device_name')->trim()->toString(),
        ]);
    }
}
