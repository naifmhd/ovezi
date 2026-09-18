<?php

namespace Database\Factories;

use App\ContactType;
use App\Models\Placeholder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Placeholder>
 */
class PlaceholderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = fake()->unique()->safeEmail();

        return [
            'created_by' => User::factory(),
            'name' => fake()->name(),
            'contact_type' => ContactType::Email,
            'contact_value' => $email,
            'contact_hash' => hash_hmac('sha256', mb_strtolower($email), config('app.key')),
            'claimed_by' => null,
            'claimed_at' => null,
        ];
    }
}
