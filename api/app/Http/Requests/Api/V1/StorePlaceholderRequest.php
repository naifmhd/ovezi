<?php

namespace App\Http\Requests\Api\V1;

use App\ContactType;
use App\Models\Placeholder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlaceholderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Placeholder::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_type' => ['required', Rule::enum(ContactType::class)],
            'contact_value' => [
                'required',
                'string',
                'max:255',
                Rule::when($this->input('contact_type') === ContactType::Email->value, ['email:rfc']),
                Rule::when($this->input('contact_type') === ContactType::Phone->value, ['regex:/^\+[1-9]\d{7,14}$/']),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $contactType = $this->string('contact_type')->trim()->lower()->toString();
        $contactValue = $this->string('contact_value')->trim()->toString();

        if ($contactType === ContactType::Email->value) {
            $contactValue = mb_strtolower($contactValue);
        } elseif ($contactType === ContactType::Phone->value) {
            $contactValue = preg_replace('/[\s()\-.]/', '', $contactValue) ?? $contactValue;
        }

        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'contact_type' => $contactType,
            'contact_value' => $contactValue,
        ]);
    }
}
