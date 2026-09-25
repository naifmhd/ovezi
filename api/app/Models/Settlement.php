<?php

namespace App\Models;

use App\ExchangeRateSource;
use Database\Factories\SettlementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

#[Fillable([
    'group_id', 'from_user_id', 'from_placeholder_id', 'to_user_id', 'to_placeholder_id',
    'amount_minor', 'currency_code', 'reporting_amount_minor', 'reporting_currency_code',
    'exchange_rate', 'exchange_rate_source', 'exchange_rate_effective_date', 'method', 'note',
    'occurred_at', 'created_by',
])]
class Settlement extends Model
{
    /** @use HasFactory<SettlementFactory> */
    use HasFactory;

    use SoftDeletes;

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id')->withTrashed();
    }

    public function fromPlaceholder(): BelongsTo
    {
        return $this->belongsTo(Placeholder::class, 'from_placeholder_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id')->withTrashed();
    }

    public function toPlaceholder(): BelongsTo
    {
        return $this->belongsTo(Placeholder::class, 'to_placeholder_id');
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

    public function activities(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    protected static function booted(): void
    {
        static::saving(function (Settlement $settlement): void {
            if (($settlement->from_user_id === null) === ($settlement->from_placeholder_id === null)) {
                throw new InvalidArgumentException('A settlement must reference exactly one sender.');
            }

            if (($settlement->to_user_id === null) === ($settlement->to_placeholder_id === null)) {
                throw new InvalidArgumentException('A settlement must reference exactly one recipient.');
            }

            $sameUser = $settlement->from_user_id !== null
                && $settlement->from_user_id === $settlement->to_user_id;
            $samePlaceholder = $settlement->from_placeholder_id !== null
                && $settlement->from_placeholder_id === $settlement->to_placeholder_id;

            if ($sameUser || $samePlaceholder) {
                throw new InvalidArgumentException('A settlement sender and recipient must be different.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'reporting_amount_minor' => 'integer',
            'exchange_rate' => 'decimal:12',
            'exchange_rate_source' => ExchangeRateSource::class,
            'exchange_rate_effective_date' => 'date',
            'occurred_at' => 'datetime',
        ];
    }
}
