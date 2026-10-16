<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\LendingContact;
use Illuminate\Database\Eloquent\Factories\Factory;

class LendingLoanFactory extends Factory
{
    public function definition(): array
    {
        return ['lending_contact_id' => LendingContact::factory(), 'created_by' => fn (array $attributes) => LendingContact::findOrFail($attributes['lending_contact_id'])->owner_id, 'direction' => 'lent', 'amount_minor' => 10000, 'currency_code' => Currency::factory(), 'occurred_on' => now()->toDateString()];
    }
}
