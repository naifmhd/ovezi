<?php

namespace App\Http\Requests\Api\V1\Auth;

use App\ConnectedAccountProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConnectSocialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        return [
            'provider' => ['required', Rule::enum(ConnectedAccountProvider::class)],
            'id_token' => ['required', 'string', 'max:16384'],
            'nonce' => ['required_if:provider,apple', 'nullable', 'string', 'between:16,255'],
            'authorization_code' => ['required_if:provider,apple', 'nullable', 'string', 'max:4096'],
            'current_password' => [Rule::requiredIf($this->user()?->password !== null), 'nullable', 'string', 'max:4096'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('current_password') && $this->user()->password !== null
                && ! Hash::check($this->string('current_password')->toString(), $this->user()->password)) {
                $validator->errors()->add('current_password', 'The current password is incorrect.');
            }
        }];
    }
}
