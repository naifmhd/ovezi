<?php

namespace Database\Factories;

use App\Models\RecurringExpense;
use App\Models\RecurringExpenseSplit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringExpenseSplit>
 */
class RecurringExpenseSplitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'recurring_expense_id' => RecurringExpense::factory(),
            'user_id' => User::factory(),
            'placeholder_id' => null,
            'split_value' => null,
        ];
    }
}
