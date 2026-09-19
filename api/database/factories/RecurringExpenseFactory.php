<?php

namespace Database\Factories;

use App\ExpenseType;
use App\Models\Currency;
use App\Models\RecurringExpense;
use App\Models\User;
use App\RecurrenceFrequency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringExpense>
 */
class RecurringExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_type' => ExpenseType::Personal,
            'group_id' => null,
            'payer_user_id' => User::factory(),
            'payer_placeholder_id' => null,
            'amount_minor' => 1500,
            'currency_code' => Currency::factory()->mvr(),
            'description' => fake()->words(3, true),
            'category' => 'home',
            'split_type' => null,
            'frequency' => RecurrenceFrequency::Monthly,
            'start_on' => today(),
            'next_occurrence_on' => today()->addMonth(),
            'ends_on' => null,
            'paused_at' => null,
            'canceled_at' => null,
            'created_by' => User::factory(),
        ];
    }
}
