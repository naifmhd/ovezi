<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Seed the currencies table with MVR and the top ten most-traded currencies.
     */
    public function run(): void
    {
        $currencies = [
            ['code' => 'MVR', 'name' => 'Maldivian Rufiyaa', 'numeric_code' => '462', 'symbol' => 'MVR', 'minor_unit_factor' => 100],
            ['code' => 'USD', 'name' => 'United States Dollar', 'numeric_code' => '840', 'symbol' => '$', 'minor_unit_factor' => 100],
            ['code' => 'EUR', 'name' => 'Euro', 'numeric_code' => '978', 'symbol' => '€', 'minor_unit_factor' => 100],
            ['code' => 'GBP', 'name' => 'British Pound Sterling', 'numeric_code' => '826', 'symbol' => '£', 'minor_unit_factor' => 100],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'numeric_code' => '392', 'symbol' => '¥', 'minor_unit_factor' => 1],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'numeric_code' => '036', 'symbol' => '$', 'minor_unit_factor' => 100],
            ['code' => 'CAD', 'name' => 'Canadian Dollar', 'numeric_code' => '124', 'symbol' => '$', 'minor_unit_factor' => 100],
            ['code' => 'CHF', 'name' => 'Swiss Franc', 'numeric_code' => '756', 'symbol' => 'Fr', 'minor_unit_factor' => 100],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'numeric_code' => '156', 'symbol' => '¥', 'minor_unit_factor' => 100],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'numeric_code' => '356', 'symbol' => '₹', 'minor_unit_factor' => 100],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'numeric_code' => '702', 'symbol' => '$', 'minor_unit_factor' => 100],
        ];

        foreach ($currencies as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                $currency,
            );
        }
    }
}
