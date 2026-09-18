<?php

namespace App\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;

class MoneyConverter
{
    public function convert(
        int $amountMinor,
        int $baseMinorUnitFactor,
        int $quoteMinorUnitFactor,
        string $rate,
    ): int {
        if ($amountMinor < 0) {
            throw new InvalidArgumentException('The amount cannot be negative.');
        }

        if ($baseMinorUnitFactor <= 0 || $quoteMinorUnitFactor <= 0) {
            throw new InvalidArgumentException('Currency minor unit factors must be positive.');
        }

        $exchangeRate = BigDecimal::of($rate);

        if ($exchangeRate->isLessThanOrEqualTo(0)) {
            throw new InvalidArgumentException('The exchange rate must be greater than zero.');
        }

        return BigDecimal::of($amountMinor)
            ->multipliedBy($exchangeRate)
            ->multipliedBy($quoteMinorUnitFactor)
            ->dividedBy($baseMinorUnitFactor, 0, RoundingMode::HalfUp)
            ->toBigInteger()
            ->toInt();
    }
}
