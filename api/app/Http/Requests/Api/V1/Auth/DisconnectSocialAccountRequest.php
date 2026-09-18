<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\ConnectedAccountProvider;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DisconnectSocialAccountRequest extends FormRequest
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
            'provider' => ['required', Rule::enum(ConnectedAccountProvider::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => mb_strtolower((string) $this->route('provider')),
        ]);
    }
}
