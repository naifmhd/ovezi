<?php

namespace App\Models;

use App\ContactType;
use Database\Factories\PlaceholderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['created_by', 'name', 'contact_type', 'contact_value', 'contact_hash', 'claimed_by', 'claimed_at'])]
#[Hidden(['contact_value', 'contact_hash'])]
class Placeholder extends Model
{
    /** @use HasFactory<PlaceholderFactory> */
    use HasFactory;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function claimedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    public function groupMemberships(): HasMany
    {
        return $this->hasMany(GroupMember::class);
    }

    public function expenseSplits(): HasMany
    {
        return $this->hasMany(ExpenseSplit::class);
    }

    protected function casts(): array
    {
        return [
            'contact_type' => ContactType::class,
            'contact_value' => 'encrypted',
            'claimed_at' => 'datetime',
        ];
    }
}
