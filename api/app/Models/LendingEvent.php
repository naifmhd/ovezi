<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['lending_loan_id', 'actor_id', 'event', 'details'])]
class LendingEvent extends Model
{
    public function loan(): BelongsTo
    {
        return $this->belongsTo(LendingLoan::class, 'lending_loan_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id')->withTrashed();
    }

    protected function casts(): array
    {
        return ['details' => 'array'];
    }
}
