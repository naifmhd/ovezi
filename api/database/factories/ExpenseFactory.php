<?php

namespace Database\Factories;

use App\ExchangeRateSource;
use App\ExpenseType;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_type' => ExpenseType::Direct,
            'group_id' => null,
            'payer_user_id' => User::factory(),
            'payer_placeholder_id' => null,
            'amount_minor' => 1500,
            'currency_code' => Currency::factory()->mvr(),
            'reporting_amount_minor' => 1500,
            'reporting_currency_code' => 'MVR',
            'exchange_rate' => null,
            'exchange_rate_source' => ExchangeRateSource::SameCurrency,
            'exchange_rate_effective_date' => null,
            'description' => fake()->words(3, true),
            'category' => 'food',
            'receipt_image_path' => null,
            'occurred_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
