<?php

namespace App\Models;

use Database\Factories\RecurringExpenseSplitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['recurring_expense_id', 'user_id', 'placeholder_id', 'split_value'])]
class RecurringExpenseSplit extends Model
{
    /** @use HasFactory<RecurringExpenseSplitFactory> */
    use HasFactory;

    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function placeholder(): BelongsTo
    {
        return $this->belongsTo(Placeholder::class);
    }

    protected static function booted(): void
    {
        static::saving(function (RecurringExpenseSplit $split): void {
            if (($split->user_id === null) === ($split->placeholder_id === null)) {
                throw new InvalidArgumentException('A recurring split must reference exactly one user or placeholder.');
            }
        });
    }

    protected function casts(): array
    {
        return ['split_value' => 'decimal:8'];
    }
}
