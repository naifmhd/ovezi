<?php

namespace App\Models;

use App\GroupMemberRole;
use Database\Factories\GroupMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable([
    'group_id', 'user_id', 'placeholder_id', 'role', 'joined_at', 'left_at',
    'notifications_muted_at',
])]
class GroupMember extends Model
{
    /** @use HasFactory<GroupMemberFactory> */
    use HasFactory;

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function placeholder(): BelongsTo
    {
        return $this->belongsTo(Placeholder::class);
    }

    protected static function booted(): void
    {
        static::saving(function (GroupMember $member): void {
            if (($member->user_id === null) === ($member->placeholder_id === null)) {
                throw new InvalidArgumentException('A group member must reference exactly one user or placeholder.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'role' => GroupMemberRole::class,
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
            'notifications_muted_at' => 'datetime',
        ];
    }
}
