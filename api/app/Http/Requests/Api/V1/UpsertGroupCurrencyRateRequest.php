<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Group;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpsertGroupCurrencyRateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        $group = $this->route('group');

        if (! $group instanceof Group || $this->user() === null) {
            return false;
        }

        return Gate::forUser($this->user())->inspect('update', $group);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rate' => [
                'required',
                'string',
                'numeric',
                'gt:0',
                'regex:/^\d{1,12}(\.\d{1,12})?$/',
            ],
        ];
    }
}
