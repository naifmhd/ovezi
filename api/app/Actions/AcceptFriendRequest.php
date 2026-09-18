<?php

namespace App\Actions;

use App\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptFriendRequest
{
    public function execute(User $user, Friendship $friendship): Friendship
    {
        return DB::transaction(function () use ($user, $friendship): Friendship {
            $locked = Friendship::query()->lockForUpdate()->findOrFail($friendship->id);

            if (! in_array($user->id, [$locked->user_id, $locked->friend_id], true)) {
                abort(404);
            }

            if ($locked->requested_by === $user->id) {
                throw ValidationException::withMessages([
                    'friendship' => 'The recipient must accept this friend request.',
                ]);
            }

            if ($locked->status === FriendshipStatus::Accepted) {
                throw ValidationException::withMessages([
                    'friendship' => 'This friend request has already been accepted.',
                ]);
            }

            $locked->update([
                'status' => FriendshipStatus::Accepted,
                'accepted_at' => now(),
            ]);

            return $locked->load(['user:id,name,email', 'friend:id,name,email']);
        });
    }
}
