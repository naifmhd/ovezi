<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Group;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreGroupInviteRequest extends FormRequest
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

        if (! $group->members()
            ->whereBelongsTo($this->user())
            ->whereNull('left_at')
            ->exists()) {
            return Response::denyAsNotFound();
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
            'invited_email' => ['nullable', 'string', 'max:255', 'email:rfc'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'invited_email' => $this->filled('invited_email')
                ? mb_strtolower($this->string('invited_email')->trim()->toString())
                : null,
        ]);
    }
}
