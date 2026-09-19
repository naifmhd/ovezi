<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecurringExpenseResource extends JsonResource
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
            'expense_type' => $this->expense_type,
            'group_id' => $this->group_id,
            'payer' => [
                'user_id' => $this->payer_user_id,
                'placeholder_id' => $this->payer_placeholder_id,
                'claimed_user_id' => $this->payerPlaceholder?->claimed_by,
                'name' => $this->payerUser?->name ?? $this->payerPlaceholder?->name,
            ],
            'amount_minor' => $this->amount_minor,
            'currency_code' => $this->currency_code,
            'description' => $this->description,
            'category' => $this->category,
            'split_type' => $this->split_type,
            'frequency' => $this->frequency,
            'start_on' => $this->start_on,
            'next_occurrence_on' => $this->next_occurrence_on,
            'ends_on' => $this->ends_on,
            'status' => $this->status(),
            'can_manage' => $request->user()?->can('update', $this->resource) ?? false,
            'splits' => $this->whenLoaded('splits', fn () => $this->splits->map(fn ($split): array => [
                'id' => $split->id,
                'user_id' => $split->user_id,
                'placeholder_id' => $split->placeholder_id,
                'claimed_user_id' => $split->placeholder?->claimed_by,
                'name' => $split->user?->name ?? $split->placeholder?->name,
                'split_value' => $split->split_value,
            ])),
            'created_by' => $this->created_by,
            'paused_at' => $this->paused_at,
            'canceled_at' => $this->canceled_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
