<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'default_currency_code' => [
                'sometimes',
                'required',
                'string',
                'size:3',
                Rule::exists('currencies', 'code')->where('is_active', true),
            ],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->hasAny(['name', 'default_currency_code'])) {
                $validator->errors()->add('profile', 'Provide at least one profile field to update.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $attributes = [];

        if ($this->has('name')) {
            $attributes['name'] = $this->string('name')->trim()->toString();
        }

        if ($this->has('default_currency_code')) {
            $attributes['default_currency_code'] = $this->string('default_currency_code')->trim()->upper()->toString();
        }

        $this->merge($attributes);
    }
}
