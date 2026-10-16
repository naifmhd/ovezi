<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LendingContactFactory extends Factory
{
    public function definition(): array
    {
        return ['owner_id' => User::factory(), 'name' => fake()->name()];
    }
}
