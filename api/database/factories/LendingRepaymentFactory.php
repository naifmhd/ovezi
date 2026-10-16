<?php

namespace Database\Factories;

use App\Models\Currency;
use App\Models\LendingContact;
use Illuminate\Database\Eloquent\Factories\Factory;

class LendingRepaymentFactory extends Factory
{
    public function definition(): array
    {
        return ['lending_contact_id' => LendingContact::factory(), 'recorded_by' => fn (array $attributes) => LendingContact::findOrFail($attributes['lending_contact_id'])->owner_id, 'amount_minor' => 100, 'currency_code' => Currency::factory(), 'direction' => 'lent', 'occurred_on' => now()->toDateString(), 'status' => 'confirmed'];
    }
}
