<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupInviteResource extends JsonResource
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
            'invited_by' => $this->invited_by,
            'invited_email' => $this->invited_email,
            'expires_at' => $this->expires_at,
            'is_expired' => $this->expires_at->isPast(),
            'is_revoked' => $this->revoked_at !== null,
            'accepted_by' => $this->accepted_by,
            'accepted_at' => $this->accepted_at,
            'created_at' => $this->created_at,
        ];
    }
}
