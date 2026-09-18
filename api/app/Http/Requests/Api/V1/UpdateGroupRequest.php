<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGroupRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('group')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required_without:reporting_currency_code', 'string', 'max:255'],
            'reporting_currency_code' => [
                'required_without:name',
                'string',
                'size:3',
                'exists:currencies,code',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->exists('name')) {
            $normalized['name'] = $this->string('name')->trim()->toString();
        }

        if ($this->exists('reporting_currency_code')) {
            $normalized['reporting_currency_code'] = $this->string('reporting_currency_code')
                ->trim()
                ->upper()
                ->toString();
        }

        $this->merge($normalized);
    }
}
