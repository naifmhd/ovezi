<?php

namespace App\Http\Requests\Api\V1;

use App\ConnectedAccountProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required_without:provider', 'nullable', 'string'],
            'provider' => ['nullable', Rule::enum(ConnectedAccountProvider::class)],
            'id_token' => ['required_with:provider', 'string', 'max:16384'],
            'nonce' => ['required_if:provider,apple', 'string', 'between:16,255'],
            'authorization_code' => ['required_if:provider,apple', 'string', 'max:4096'],
        ];
    }
}
