<?php

namespace App\Models;

use App\ExchangeRateSource;
use App\ExpenseType;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

#[Fillable([
    'recurring_expense_id', 'recurring_occurrence_on', 'expense_type', 'group_id',
    'payer_user_id', 'payer_placeholder_id', 'amount_minor',
    'currency_code', 'reporting_amount_minor', 'reporting_currency_code', 'exchange_rate',
    'exchange_rate_source', 'exchange_rate_effective_date', 'description', 'category',
    'receipt_image_path', 'occurred_at', 'created_by',
])]
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    use SoftDeletes;

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function recurringExpense(): BelongsTo
    {
        return $this->belongsTo(RecurringExpense::class);
    }

    public function payerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_user_id')->withTrashed();
    }

    public function payerPlaceholder(): BelongsTo
    {
        return $this->belongsTo(Placeholder::class, 'payer_placeholder_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code');
    }

    public function reportingCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'reporting_currency_code');
    }

    public function splits(): HasMany
    {
        return $this->hasMany(ExpenseSplit::class)->orderBy('id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    protected static function booted(): void
    {
        static::saving(function (Expense $expense): void {
            if (($expense->payer_user_id === null) === ($expense->payer_placeholder_id === null)) {
                throw new InvalidArgumentException('An expense must reference exactly one payer.');
            }

            $isGroupExpense = $expense->expense_type === ExpenseType::Group
                || $expense->expense_type === ExpenseType::Group->value;

            if ($isGroupExpense !== ($expense->group_id !== null)) {
                throw new InvalidArgumentException('Only group expenses may reference a group.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'expense_type' => ExpenseType::class,
            'amount_minor' => 'integer',
            'reporting_amount_minor' => 'integer',
            'exchange_rate' => 'decimal:12',
            'exchange_rate_source' => ExchangeRateSource::class,
            'exchange_rate_effective_date' => 'date',
            'occurred_at' => 'datetime',
            'recurring_occurrence_on' => 'date',
        ];
    }
}
