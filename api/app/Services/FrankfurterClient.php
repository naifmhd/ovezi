<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;
use UnexpectedValueException;

class FrankfurterClient
{
    /**
     * @param  list<string>  $quoteCurrencyCodes
     * @return list<array{date: string, base: string, quote: string, rate: int|float|string}>
     */
    public function latestRates(string $baseCurrencyCode, array $quoteCurrencyCodes): array
    {
        $response = $this->request()
            ->get('/rates', [
                'base' => $baseCurrencyCode,
                'quotes' => implode(',', $quoteCurrencyCodes),
            ])
            ->throw();

        $rates = $response->json();

        if (! is_array($rates)) {
            throw new UnexpectedValueException('Frankfurter returned an invalid rates response.');
        }

        foreach ($rates as $rate) {
            if (! is_array($rate)
                || ! isset($rate['date'], $rate['base'], $rate['quote'], $rate['rate'])
                || ! is_string($rate['date'])
                || ! is_string($rate['base'])
                || ! is_string($rate['quote'])
                || ! is_numeric($rate['rate'])) {
                throw new UnexpectedValueException('Frankfurter returned an invalid rate record.');
            }
        }

        return $rates;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.frankfurter.url'), '/'))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout(10)
            ->retry(
                [200, 500, 1000],
                0,
                fn (Throwable $exception): bool => $exception instanceof ConnectionException
                    || ($exception instanceof RequestException
                        && ($exception->response->serverError() || $exception->response->tooManyRequests())),
            );
    }
}
