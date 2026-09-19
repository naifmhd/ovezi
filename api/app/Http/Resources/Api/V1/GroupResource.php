<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
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
            'name' => $this->name,
            'reporting_currency_code' => $this->reporting_currency_code,
            'has_photo' => $this->photo_path !== null,
            'photo_url' => $this->photo_path === null
                ? null
                : route('api.v1.groups.photo.show', $this->resource)
                    .'?v='.substr(hash('sha256', $this->photo_path), 0, 12),
            'created_by' => $this->created_by,
            'active_member_count' => $this->whenCounted('activeMembers'),
            'is_archived' => $this->archived_at !== null,
            'archived_at' => $this->archived_at,
            'members' => GroupMemberResource::collection($this->whenLoaded('activeMembers')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
