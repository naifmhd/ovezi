<?php

namespace App\Http\Requests\Api\V1;

use App\ContactType;
use App\Models\Placeholder;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePlaceholderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        $placeholder = $this->route('placeholder');

        if (! $placeholder instanceof Placeholder || $this->user() === null) {
            return false;
        }

        return Gate::forUser($this->user())->inspect('update', $placeholder);
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
            'contact_type' => ['sometimes', 'required', Rule::enum(ContactType::class), 'required_with:contact_value'],
            'contact_value' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'required_with:contact_type',
                Rule::when($this->input('contact_type') === ContactType::Email->value, ['email:rfc']),
                Rule::when($this->input('contact_type') === ContactType::Phone->value, ['regex:/^\+[1-9]\d{7,14}$/']),
            ],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->hasAny(['name', 'contact_type', 'contact_value'])) {
                $validator->errors()->add('placeholder', 'Provide at least one field to update.');
            }

            if ($this->has('contact_type') !== $this->has('contact_value')) {
                $validator->errors()->add('contact_value', 'Contact type and contact value must be changed together.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $attributes = [];

        if ($this->has('name')) {
            $attributes['name'] = $this->string('name')->trim()->toString();
        }

        if ($this->has('contact_type')) {
            $attributes['contact_type'] = $this->string('contact_type')->trim()->lower()->toString();
        }

        if ($this->has('contact_value')) {
            $contactValue = $this->string('contact_value')->trim()->toString();
            $contactType = $attributes['contact_type'] ?? $this->string('contact_type')->toString();
            $attributes['contact_value'] = match ($contactType) {
                ContactType::Email->value => mb_strtolower($contactValue),
                ContactType::Phone->value => preg_replace('/[\s()\-.]/', '', $contactValue) ?? $contactValue,
                default => $contactValue,
            };
        }

        $this->merge($attributes);
    }
}
