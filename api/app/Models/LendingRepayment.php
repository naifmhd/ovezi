<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['lending_contact_id', 'recorded_by', 'confirmed_by', 'amount_minor', 'currency_code', 'direction', 'occurred_on', 'note', 'status'])]
class LendingRepayment extends Model
{
    use HasFactory;

    public function contact(): BelongsTo
    {
        return $this->belongsTo(LendingContact::class, 'lending_contact_id');
    }

    public function loans(): BelongsToMany
    {
        return $this->belongsToMany(LendingLoan::class, 'lending_repayment_allocations')->withPivot('amount_minor');
    }

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'occurred_on' => 'date'];
    }
}
