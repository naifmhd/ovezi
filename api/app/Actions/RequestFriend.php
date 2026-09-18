<?php

namespace App\Actions;

use App\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequestFriend
{
    public function execute(User $requester, string $email): Friendship
    {
        $target = User::query()->where('email', mb_strtolower($email))->first();

        if ($target === null) {
            throw ValidationException::withMessages([
                'email' => 'No Ovezi account was found for this email.',
            ]);
        }

        if ($target->id === $requester->id) {
            throw ValidationException::withMessages([
                'email' => 'You cannot add yourself as a friend.',
            ]);
        }

        [$userId, $friendId] = $requester->id < $target->id
            ? [$requester->id, $target->id]
            : [$target->id, $requester->id];

        return DB::transaction(function () use ($requester, $userId, $friendId): Friendship {
            $existing = Friendship::query()
                ->where('user_id', $userId)
                ->where('friend_id', $friendId)
                ->lockForUpdate()
                ->first();

            if ($existing?->status === FriendshipStatus::Accepted) {
                throw ValidationException::withMessages(['email' => 'You are already friends.']);
            }

            if ($existing !== null) {
                $message = $existing->requested_by === $requester->id
                    ? 'Your friend request is already pending.'
                    : 'This person already sent you a friend request.';

                throw ValidationException::withMessages(['email' => $message]);
            }

            return Friendship::query()->create([
                'user_id' => $userId,
                'friend_id' => $friendId,
                'requested_by' => $requester->id,
                'status' => FriendshipStatus::Pending,
            ])->load(['user:id,name,email', 'friend:id,name,email']);
        });
    }
}
