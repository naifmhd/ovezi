<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Group;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreGroupMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        $group = $this->route('group');

        if (! $group instanceof Group) {
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
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
            'placeholder_id' => ['nullable', 'integer', 'exists:placeholders,id'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['user_id', 'placeholder_id'])) {
                return;
            }

            if (($this->input('user_id') === null) === ($this->input('placeholder_id') === null)) {
                $validator->errors()->add('member', 'Select exactly one user or placeholder.');
            }
        }];
    }
}
