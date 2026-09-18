<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FriendshipResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentUserId = $request->user()->id;
        $otherUser = $this->user_id === $currentUserId ? $this->friend : $this->user;

        return [
            'id' => $this->id,
            'friend' => [
                'id' => $otherUser->id,
                'name' => $otherUser->name,
                'email' => $otherUser->email,
            ],
            'status' => $this->status,
            'direction' => $this->requested_by === $currentUserId ? 'outgoing' : 'incoming',
            'accepted_at' => $this->accepted_at,
            'created_at' => $this->created_at,
        ];
    }
}
