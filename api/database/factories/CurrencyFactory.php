<?php

namespace Database\Factories;

use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->currencyCode(),
            'name' => fake()->currencyCode().' currency',
            'numeric_code' => null,
            'symbol' => null,
            'minor_unit_factor' => 100,
            'is_active' => true,
        ];
    }

    public function mvr(): static
    {
        return $this->state(fn (): array => [
            'code' => 'MVR',
            'name' => 'Maldivian Rufiyaa',
            'numeric_code' => '462',
            'symbol' => 'MVR',
            'minor_unit_factor' => 100,
        ]);
    }

    public function usd(): static
    {
        return $this->state(fn (): array => [
            'code' => 'USD',
            'name' => 'United States Dollar',
            'numeric_code' => '840',
            'symbol' => '$',
            'minor_unit_factor' => 100,
        ]);
    }
}
