<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\Group;
use App\Models\GroupCurrencyRate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GroupCurrencyRate>
 */
class GroupCurrencyRateFactory extends Factory
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
            'base_currency_code' => Currency::factory()->usd(),
            'quote_currency_code' => fn (array $attributes): string => Group::query()
                ->findOrFail($attributes['group_id'])
                ->reporting_currency_code,
            'rate' => '15.500000000000',
            'created_by' => User::factory(),
        ];
    }
}
