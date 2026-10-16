<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LendingLoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $owner = $this->contact->owner_id === $request->user()->id;
        $direction = $owner ? $this->direction : ($this->direction === 'lent' ? 'borrowed' : 'lent');

        return [
            'id' => $this->id, 'contact_id' => $this->lending_contact_id, 'direction' => $direction,
            'amount_minor' => $this->amount_minor, 'repaid_minor' => $this->repaid_minor,
            'outstanding_minor' => $this->voided_at ? 0 : $this->amount_minor - $this->repaid_minor,
            'pending_minor' => (int) ($this->pending_minor ?? 0),
            'currency_code' => $this->currency_code, 'description' => $this->description,
            'private_note' => $this->created_by === $request->user()->id ? $this->private_note : null,
            'occurred_on' => $this->occurred_on->toDateString(), 'due_on' => $this->due_on?->toDateString(),
            'sharing' => $this->shared_with !== null ? 'shared' : ($this->shared_at ? 'invited' : 'private'),
            'acknowledgement' => $this->acknowledgement, 'voided_at' => $this->voided_at,
            'void_requested_by' => $this->void_requested_by, 'is_creator' => $this->created_by === $request->user()->id,
            'can_confirm' => $this->shared_with === null ? $owner : $direction === 'lent',
        ];
    }
}
