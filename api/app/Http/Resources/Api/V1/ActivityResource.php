<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class ActivityResource extends JsonResource
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
            'actor' => $this->whenLoaded('actor', fn (): ?array => $this->actor === null ? null : [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ]),
            'subject' => [
                'type' => Str::of(class_basename($this->subject_type))->snake()->toString(),
                'id' => $this->subject_id,
            ],
            'event' => $this->event,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
        ];
    }
}
