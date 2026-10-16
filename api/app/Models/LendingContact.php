<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['owner_id', 'name', 'email', 'email_hash', 'connected_user_id', 'invitation_status', 'invitation_expires_at', 'disconnected_at'])]
#[Hidden(['email', 'email_hash'])]
class LendingContact extends Model
{
    use HasFactory;

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id')->withTrashed();
    }

    public function loans(): HasMany
    {
        return $this->hasMany(LendingLoan::class);
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LendingRepayment::class);
    }

    public function connectedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_user_id')->withTrashed();
    }

    protected function casts(): array
    {
        return ['email' => 'encrypted', 'invitation_expires_at' => 'datetime', 'disconnected_at' => 'datetime'];
    }
}
