<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlaceholderResource extends JsonResource
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
            'contact_type' => $this->contact_type,
            'is_claimed' => $this->claimed_by !== null,
            'claimed_at' => $this->claimed_at,
            'created_at' => $this->created_at,
        ];
    }
}
