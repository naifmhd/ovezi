<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LendingContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->owner_id === $request->user()->id;

        return [
            'is_pinned' => $this->when(array_key_exists('pin_id', $this->resource->getAttributes()), fn () => $this->resource->getAttribute('pin_id') !== null),
            'id' => $this->id, 'name' => $owner ? $this->name : $this->owner->name,
            'email' => $owner ? $this->email : null, 'is_owner' => $owner,
            'is_connected' => $this->connected_user_id !== null && $this->disconnected_at === null,
            'invitation_status' => $owner ? ($this->invitation_status === 'pending' && $this->invitation_expires_at?->isPast() ? 'expired' : $this->invitation_status) : null,
            'disconnected_at' => $this->disconnected_at,
            'next_due_on' => $this->resource->getAttribute('next_due_on'),
            'last_currency_code' => $this->resource->getAttribute('last_currency_code'),
            'balances' => collect($this->resource->getAttribute('balances') ?? [])->map(fn (array $balance) => [
                'currency_code' => $balance['currency_code'],
                'direction' => $owner ? $balance['direction'] : ($balance['direction'] === 'lent' ? 'borrowed' : 'lent'),
                'amount_minor' => (int) $balance['lent_minor'], 'repaid_minor' => (int) $balance['repaid_minor'],
                'outstanding_minor' => (int) $balance['outstanding_minor'],
            ])->values(),
        ];
    }
}
