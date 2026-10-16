<?php

namespace App\Http\Resources\Api\V1;

use App\Models\LendingEvent;
use Illuminate\Http\Request;

class TimelineActivityResource extends ActivityResource
{
    public function toArray(Request $request): array
    {
        if (! $this->resource instanceof LendingEvent) {
            return [...parent::toArray($request), 'timeline_id' => 'activity:'.$this->id, 'source' => $this->group_id ? 'groups' : 'personal'];
        }
        $loan = $this->loan;
        $contact = $loan->contact;
        $owner = $contact->owner_id === $request->user()->id;

        return [
            'id' => $this->id, 'timeline_id' => 'lending:'.$this->id, 'source' => 'lending',
            'group_id' => null, 'group' => null,
            'actor' => $this->actor ? ['id' => $this->actor->id, 'name' => $this->actor->name] : null,
            'person' => ['id' => $contact->id, 'name' => $owner ? $contact->name : ($contact->owner?->name ?? 'Deleted member')],
            'subject' => ['type' => 'lending_loan', 'id' => $loan->id],
            'event' => $this->event,
            'metadata' => [
                'amount_minor' => (int) ($this->details['amount_minor'] ?? $loan->amount_minor),
                'currency_code' => $loan->currency_code, 'description' => $loan->description,
                'direction' => $owner ? $loan->direction : ($loan->direction === 'lent' ? 'borrowed' : 'lent'),
            ],
            'created_at' => $this->created_at,
        ];
    }
}
