<?php

namespace App\Models;

use App\SplitType;
use Database\Factories\ExpenseSplitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable([
    'expense_id', 'user_id', 'placeholder_id', 'amount_owed_minor', 'reporting_amount_owed_minor',
    'split_type', 'split_value',
])]
class ExpenseSplit extends Model
{
    /** @use HasFactory<ExpenseSplitFactory> */
    use HasFactory;

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
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
        static::saving(function (ExpenseSplit $split): void {
            if (($split->user_id === null) === ($split->placeholder_id === null)) {
                throw new InvalidArgumentException('An expense split must reference exactly one user or placeholder.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount_owed_minor' => 'integer',
            'reporting_amount_owed_minor' => 'integer',
            'split_type' => SplitType::class,
            'split_value' => 'decimal:8',
        ];
    }
}
