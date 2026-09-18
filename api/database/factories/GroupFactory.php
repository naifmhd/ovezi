<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'reporting_currency_code' => Currency::factory()->mvr(),
            'photo_path' => null,
            'created_by' => User::factory(),
            'archived_at' => null,
        ];
    }
}
