<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\User;
use App\SplitType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseSplit>
 */
class ExpenseSplitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_id' => Expense::factory(),
            'user_id' => User::factory(),
            'placeholder_id' => null,
            'amount_owed_minor' => 750,
            'reporting_amount_owed_minor' => 750,
            'split_type' => SplitType::Equal,
            'split_value' => null,
        ];
    }
}
