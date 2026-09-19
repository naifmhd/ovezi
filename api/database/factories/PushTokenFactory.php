<?php

namespace Database\Factories;

use App\Models\PushToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushToken>
 */
class PushTokenFactory extends Factory
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
            'expo_push_token' => 'ExponentPushToken['.fake()->unique()->regexify('[A-Za-z0-9_-]{22}').']',
            'platform' => fake()->randomElement(['ios', 'android']),
            'device_name' => fake()->word(),
            'last_seen_at' => now(),
            'revoked_at' => null,
        ];
    }
}
