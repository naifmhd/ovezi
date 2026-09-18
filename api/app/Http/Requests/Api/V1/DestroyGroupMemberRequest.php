<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyGroupMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool|Response
    {
        $group = $this->route('group');
        $member = $this->route('member');

        if (! $group instanceof Group || ! $member instanceof GroupMember || $this->user() === null) {
            return false;
        }

        if ($member->group_id !== $group->id) {
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
        ];
    }
}
