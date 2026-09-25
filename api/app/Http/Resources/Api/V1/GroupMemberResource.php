<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'group_id' => $this->group_id,
            'role' => $this->role,
            'user' => $this->when($this->user_id !== null, fn (): array => [
                'id' => $this->user_id,
                'name' => $this->user?->name,
                'is_deleted' => $this->user?->trashed() ?? true,
            ]),
            'placeholder' => $this->when($this->placeholder_id !== null, fn (): array => [
                'id' => $this->placeholder_id,
                'name' => $this->placeholder?->name,
            ]),
            'joined_at' => $this->joined_at,
            'left_at' => $this->left_at,
        ];
    }
}
