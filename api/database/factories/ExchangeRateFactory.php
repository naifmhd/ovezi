<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'base_currency_code' => Currency::factory()->usd(),
            'quote_currency_code' => Currency::factory()->mvr(),
            'rate' => '15.420000000000',
            'provider' => 'frankfurter',
            'effective_date' => now()->toDateString(),
            'fetched_at' => now(),
        ];
    }
}
