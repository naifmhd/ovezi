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
