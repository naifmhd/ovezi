<?php

namespace Database\Factories;

use App\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Friendship>
 */
class FriendshipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'friend_id' => User::factory(),
            'requested_by' => null,
            'status' => FriendshipStatus::Pending,
            'accepted_at' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Friendship $friendship): void {
            $userId = min($friendship->user_id, $friendship->friend_id);
            $friendId = max($friendship->user_id, $friendship->friend_id);

            $friendship->user_id = $userId;
            $friendship->friend_id = $friendId;
            $friendship->requested_by = $userId;
        });
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => FriendshipStatus::Accepted,
            'accepted_at' => now(),
        ]);
    }
}
