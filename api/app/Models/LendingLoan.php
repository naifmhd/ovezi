<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['lending_contact_id', 'created_by', 'direction', 'amount_minor', 'currency_code', 'occurred_on', 'due_on', 'description', 'private_note', 'shared_at', 'shared_with', 'acknowledgement', 'repaid_minor', 'voided_at', 'void_requested_by'])]
#[Hidden(['private_note'])]
class LendingLoan extends Model
{
    use HasFactory;

    public function contact(): BelongsTo
    {
        return $this->belongsTo(LendingContact::class, 'lending_contact_id');
    }

    public function repayments(): BelongsToMany
    {
        return $this->belongsToMany(LendingRepayment::class, 'lending_repayment_allocations')->withPivot('amount_minor');
    }

    public function events(): HasMany
    {
        return $this->hasMany(LendingEvent::class);
    }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'repaid_minor' => 'integer', 'occurred_on' => 'date', 'due_on' => 'date', 'shared_at' => 'datetime', 'voided_at' => 'datetime', 'private_note' => 'encrypted'];
    }
}
