<?php

namespace App\Models;

use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'numeric_code', 'symbol', 'minor_unit_factor', 'is_active'])]
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'code';

    public function baseExchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'base_currency_code');
    }

    public function quoteExchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'quote_currency_code');
    }

    protected function casts(): array
    {
        return [
            'minor_unit_factor' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
