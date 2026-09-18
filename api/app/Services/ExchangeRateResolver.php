<?php

namespace App\Services;

use App\ExchangeRateSource;
use App\Models\ExchangeRate;
use App\Models\Group;
use Brick\Math\BigDecimal;
use Carbon\CarbonInterface;
use InvalidArgumentException;
use RuntimeException;

class ExchangeRateResolver
{
    public function resolve(
        string $baseCurrencyCode,
        string $quoteCurrencyCode,
        CarbonInterface $occurredAt,
        ?Group $group = null,
        ?string $expenseRate = null,
    ): ResolvedExchangeRate {
        if ($baseCurrencyCode === $quoteCurrencyCode) {
            return new ResolvedExchangeRate(null, ExchangeRateSource::SameCurrency, null);
        }

        if ($expenseRate !== null) {
            $this->ensurePositiveRate($expenseRate);

            return new ResolvedExchangeRate(
                $expenseRate,
                ExchangeRateSource::Expense,
                $occurredAt->toImmutable()->startOfDay(),
            );
        }

        $groupRate = $group?->currencyRates()
            ->where('base_currency_code', $baseCurrencyCode)
            ->where('quote_currency_code', $quoteCurrencyCode)
            ->latest('id')
            ->first();

        if ($groupRate !== null) {
            return new ResolvedExchangeRate(
                $groupRate->rate,
                ExchangeRateSource::Group,
                $groupRate->updated_at?->toImmutable()->startOfDay(),
            );
        }

        $effectiveDate = $occurredAt->toImmutable()->startOfDay();
        $providerRate = ExchangeRate::query()
            ->where('base_currency_code', $baseCurrencyCode)
            ->where('quote_currency_code', $quoteCurrencyCode)
            ->whereDate('effective_date', '<=', $effectiveDate)
            ->whereDate('effective_date', '>=', $effectiveDate->subDays(7))
            ->latest('effective_date')
            ->latest('fetched_at')
            ->first();

        if ($providerRate === null) {
            throw new RuntimeException("No usable exchange rate is available for {$baseCurrencyCode}/{$quoteCurrencyCode}.");
        }

        return new ResolvedExchangeRate(
            $providerRate->rate,
            ExchangeRateSource::Provider,
            $providerRate->effective_date->toImmutable(),
        );
    }

    private function ensurePositiveRate(string $rate): void
    {
        if (BigDecimal::of($rate)->isLessThanOrEqualTo(0)) {
            throw new InvalidArgumentException('The exchange rate must be greater than zero.');
        }
    }
}
