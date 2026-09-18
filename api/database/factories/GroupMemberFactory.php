<?php

namespace Database\Factories;

use App\GroupMemberRole;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupMember>
 */
class GroupMemberFactory extends Factory
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
            'user_id' => User::factory(),
            'placeholder_id' => null,
            'role' => GroupMemberRole::Member,
            'joined_at' => now(),
            'left_at' => null,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (): array => [
            'role' => GroupMemberRole::Owner,
        ]);
    }
}
