<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
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
            'reporting_amount_minor' => $this->reporting_amount_minor,
            'reporting_currency_code' => $this->reporting_currency_code,
            'exchange_rate' => $this->exchange_rate,
            'exchange_rate_source' => $this->exchange_rate_source,
            'exchange_rate_effective_date' => $this->exchange_rate_effective_date,
            'description' => $this->description,
            'category' => $this->category,
            'has_receipt' => $this->receipt_image_path !== null,
            'receipt_url' => $this->receipt_image_path === null
                ? null
                : route('api.v1.expenses.receipt.show', $this->resource),
            'occurred_at' => $this->occurred_at,
            'created_by' => $this->created_by,
            'splits' => $this->whenLoaded('splits', fn () => $this->splits->map(fn ($split): array => [
                'id' => $split->id,
                'user_id' => $split->user_id,
                'placeholder_id' => $split->placeholder_id,
                'claimed_user_id' => $split->placeholder?->claimed_by,
                'name' => $split->user?->name ?? $split->placeholder?->name,
                'amount_owed_minor' => $split->amount_owed_minor,
                'reporting_amount_owed_minor' => $split->reporting_amount_owed_minor,
                'split_type' => $split->split_type,
                'split_value' => $split->split_value,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
