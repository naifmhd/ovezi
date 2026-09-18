<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaceholderClaimResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact_type' => $this->contact_type,
            'created_by' => $this->whenLoaded('creator', fn (): ?array => $this->creator === null
                ? null
                : [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                ]),
            'groups' => $this->whenLoaded('groupMemberships', fn () => $this->groupMemberships
                ->whereNull('left_at')
                ->map(fn ($membership): array => [
                    'id' => $membership->group_id,
                    'name' => $membership->group?->name,
                ])
                ->values()),
            'expense_count' => $this->whenCounted('expenseSplits'),
            'group_count' => $this->whenCounted('groupMemberships'),
            'is_claimed' => $this->claimed_by !== null,
            'claimed_at' => $this->claimed_at,
            'created_at' => $this->created_at,
        ];
    }
}
