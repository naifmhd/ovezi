<?php

namespace Database\Factories;

use App\ExchangeRateSource;
use App\Models\Currency;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Settlement>
 */
class SettlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => null,
            'from_user_id' => User::factory(),
            'from_placeholder_id' => null,
            'to_user_id' => User::factory(),
            'to_placeholder_id' => null,
            'amount_minor' => 1500,
            'currency_code' => Currency::factory()->mvr(),
            'reporting_amount_minor' => 1500,
            'reporting_currency_code' => 'MVR',
            'exchange_rate' => null,
            'exchange_rate_source' => ExchangeRateSource::SameCurrency,
            'exchange_rate_effective_date' => null,
            'method' => 'bank_transfer',
            'note' => null,
            'occurred_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
