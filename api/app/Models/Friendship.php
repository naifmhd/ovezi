<?php

namespace App\Models;

use App\FriendshipStatus;
use Database\Factories\FriendshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['user_id', 'friend_id', 'requested_by', 'status', 'accepted_at'])]
class Friendship extends Model
{
    /** @use HasFactory<FriendshipFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function friend(): BelongsTo
    {
        return $this->belongsTo(User::class, 'friend_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    protected static function booted(): void
    {
        static::saving(function (Friendship $friendship): void {
            if ($friendship->user_id >= $friendship->friend_id) {
                throw new InvalidArgumentException('Friendship user IDs must use canonical ascending order.');
            }

            if (! in_array($friendship->requested_by, [$friendship->user_id, $friendship->friend_id], true)) {
                throw new InvalidArgumentException('The friendship requester must be one of the two users.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => FriendshipStatus::class,
            'accepted_at' => 'datetime',
        ];
    }
}
