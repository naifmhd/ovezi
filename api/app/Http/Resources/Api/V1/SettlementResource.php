<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettlementResource extends JsonResource
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
            'from' => [
                'user_id' => $this->from_user_id,
                'placeholder_id' => $this->from_placeholder_id,
            ],
            'to' => [
                'user_id' => $this->to_user_id,
                'placeholder_id' => $this->to_placeholder_id,
            ],
            'amount_minor' => $this->amount_minor,
            'currency_code' => $this->currency_code,
            'reporting_amount_minor' => $this->reporting_amount_minor,
            'reporting_currency_code' => $this->reporting_currency_code,
            'exchange_rate' => $this->exchange_rate,
            'exchange_rate_source' => $this->exchange_rate_source,
            'exchange_rate_effective_date' => $this->exchange_rate_effective_date,
            'method' => $this->method,
            'note' => $this->note,
            'occurred_at' => $this->occurred_at,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
