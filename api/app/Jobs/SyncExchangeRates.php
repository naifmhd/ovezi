<?php

namespace App\Jobs;

use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Services\FrankfurterClient;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class SyncExchangeRates implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function handle(FrankfurterClient $frankfurter): void
    {
        $currencyCodes = Currency::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->pluck('code')
            ->all();

        if (count($currencyCodes) < 2) {
            return;
        }

        $pivotCurrencyCode = 'EUR';
        $providerRates = $frankfurter->latestRates($pivotCurrencyCode, $currencyCodes);
        $pivotRates = [$pivotCurrencyCode => BigDecimal::one()];
        $effectiveDates = [];

        foreach ($providerRates as $providerRate) {
            if (mb_strtoupper($providerRate['base']) !== $pivotCurrencyCode) {
                throw new UnexpectedValueException('Frankfurter returned a rate with an unexpected base currency.');
            }

            $quoteCurrencyCode = mb_strtoupper($providerRate['quote']);

            if (! in_array($quoteCurrencyCode, $currencyCodes, true)) {
                continue;
            }

            $rate = BigDecimal::of((string) $providerRate['rate']);

            if ($rate->isLessThanOrEqualTo(0)) {
                throw new UnexpectedValueException('Frankfurter returned a non-positive exchange rate.');
            }

            $pivotRates[$quoteCurrencyCode] = $rate;
            $effectiveDates[$quoteCurrencyCode] = CarbonImmutable::parse($providerRate['date'])->startOfDay();
        }

        $missingCurrencyCodes = array_diff($currencyCodes, array_keys($pivotRates));

        if ($missingCurrencyCodes !== []) {
            throw new UnexpectedValueException(
                'Frankfurter did not return rates for: '.implode(', ', $missingCurrencyCodes).'.',
            );
        }

        $fetchedAt = now();
        $records = [];

        foreach ($currencyCodes as $baseCurrencyCode) {
            foreach ($currencyCodes as $quoteCurrencyCode) {
                if ($baseCurrencyCode === $quoteCurrencyCode) {
                    continue;
                }

                $effectiveDate = $this->effectiveDate(
                    $effectiveDates[$baseCurrencyCode] ?? $effectiveDates[$quoteCurrencyCode],
                    $effectiveDates[$quoteCurrencyCode] ?? $effectiveDates[$baseCurrencyCode],
                );
                $records[] = [
                    'base_currency_code' => $baseCurrencyCode,
                    'quote_currency_code' => $quoteCurrencyCode,
                    'rate' => (string) $pivotRates[$quoteCurrencyCode]
                        ->dividedBy($pivotRates[$baseCurrencyCode], 12, RoundingMode::HalfUp),
                    'provider' => 'frankfurter',
                    'effective_date' => $effectiveDate->toDateString(),
                    'fetched_at' => $fetchedAt,
                    'created_at' => $fetchedAt,
                    'updated_at' => $fetchedAt,
                ];
            }
        }

        DB::transaction(function () use ($records): void {
            foreach (array_chunk($records, 500) as $recordChunk) {
                ExchangeRate::query()->upsert(
                    $recordChunk,
                    ['base_currency_code', 'quote_currency_code', 'provider', 'effective_date'],
                    ['rate', 'fetched_at', 'updated_at'],
                );
            }
        });
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    private function effectiveDate(CarbonImmutable $baseDate, CarbonImmutable $quoteDate): CarbonImmutable
    {
        return $baseDate->lessThan($quoteDate) ? $baseDate : $quoteDate;
    }
}
