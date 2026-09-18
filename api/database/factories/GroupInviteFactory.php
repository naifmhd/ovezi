<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GroupInvite>
 */
class GroupInviteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'invited_by' => User::factory(),
            'token_hash' => hash('sha256', Str::random(64)),
            'invited_email' => fake()->optional()->safeEmail(),
            'expires_at' => now()->addDays(7),
            'revoked_at' => null,
            'accepted_by' => null,
            'accepted_at' => null,
        ];
    }
}
