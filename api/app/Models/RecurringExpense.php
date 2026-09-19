<?php

namespace App\Models;

use App\ExpenseType;
use App\RecurrenceFrequency;
use App\SplitType;
use Database\Factories\RecurringExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable([
    'expense_type', 'group_id', 'payer_user_id', 'payer_placeholder_id', 'amount_minor',
    'currency_code', 'description', 'category', 'split_type', 'frequency', 'start_on',
    'next_occurrence_on', 'ends_on', 'paused_at', 'canceled_at', 'created_by',
])]
class RecurringExpense extends Model
{
    /** @use HasFactory<RecurringExpenseFactory> */
    use HasFactory;

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function payerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id');
    }

    public function payerPlaceholder(): BelongsTo
    {
        return $this->belongsTo(Placeholder::class, 'payer_placeholder_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function splits(): HasMany
    {
        return $this->hasMany(RecurringExpenseSplit::class)->orderBy('id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class)->orderByDesc('recurring_occurrence_on');
    }

    public function status(): string
    {
        if ($this->canceled_at !== null) {
            return 'canceled';
        }

        if ($this->paused_at !== null) {
            return 'paused';
        }

        return $this->next_occurrence_on === null ? 'completed' : 'active';
    }

    protected static function booted(): void
    {
        static::saving(function (RecurringExpense $recurringExpense): void {
            if (($recurringExpense->payer_user_id === null) === ($recurringExpense->payer_placeholder_id === null)) {
                throw new InvalidArgumentException('A recurring expense must reference exactly one payer.');
            }

            $isGroupExpense = $recurringExpense->expense_type === ExpenseType::Group
                || $recurringExpense->expense_type === ExpenseType::Group->value;

            if ($isGroupExpense !== ($recurringExpense->group_id !== null)) {
                throw new InvalidArgumentException('Only recurring group expenses may reference a group.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'expense_type' => ExpenseType::class,
            'amount_minor' => 'integer',
            'split_type' => SplitType::class,
            'frequency' => RecurrenceFrequency::class,
            'start_on' => 'date',
            'next_occurrence_on' => 'date',
            'ends_on' => 'date',
            'paused_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }
}
