<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationPreferenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'expense_created' => $this->expense_created,
            'payment_received' => $this->payment_received,
            'settle_up_reminders' => $this->settle_up_reminders,
            'groups' => $this->user->groupMemberships
                ->sortBy(fn ($membership): string => mb_strtolower($membership->group->name))
                ->values()
                ->map(fn ($membership): array => [
                    'id' => $membership->group_id,
                    'name' => $membership->group->name,
                    'muted' => $membership->notifications_muted_at !== null,
                ]),
        ];
    }
}
