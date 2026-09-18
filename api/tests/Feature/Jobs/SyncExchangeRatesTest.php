<?php

use App\Jobs\SyncExchangeRates;
use App\Models\Currency;
use App\Services\FrankfurterClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('stores daily rates for every active currency pair', function () {
    Currency::factory()->usd()->create();
    Currency::factory()->mvr()->create();
    Http::preventStrayRequests();
    Http::fake([
        'api.frankfurter.dev/v2/rates*' => Http::response([
            ['date' => '2026-09-18', 'base' => 'EUR', 'quote' => 'MVR', 'rate' => 30.84],
            ['date' => '2026-09-18', 'base' => 'EUR', 'quote' => 'USD', 'rate' => 2],
        ]),
    ]);

    (new SyncExchangeRates)->handle(app(FrankfurterClient::class));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.frankfurter.dev/v2/rates?base=EUR&quotes=MVR%2CUSD');
    $this->assertDatabaseHas('exchange_rates', [
        'base_currency_code' => 'USD',
        'quote_currency_code' => 'MVR',
        'rate' => 15.42,
        'provider' => 'frankfurter',
        'effective_date' => '2026-09-18',
    ]);
    $this->assertDatabaseHas('exchange_rates', [
        'base_currency_code' => 'MVR',
        'quote_currency_code' => 'USD',
        'provider' => 'frankfurter',
        'effective_date' => '2026-09-18',
    ]);
    $this->assertDatabaseCount('exchange_rates', 2);
});

it('updates an existing daily snapshot instead of duplicating it', function () {
    Currency::factory()->usd()->create();
    Currency::factory()->mvr()->create();
    Http::preventStrayRequests();
    Http::fakeSequence('api.frankfurter.dev/v2/rates*')
        ->push([
            ['date' => '2026-09-18', 'base' => 'EUR', 'quote' => 'MVR', 'rate' => 30],
            ['date' => '2026-09-18', 'base' => 'EUR', 'quote' => 'USD', 'rate' => 2],
        ])
        ->push([
            ['date' => '2026-09-18', 'base' => 'EUR', 'quote' => 'MVR', 'rate' => 32],
            ['date' => '2026-09-18', 'base' => 'EUR', 'quote' => 'USD', 'rate' => 2],
        ]);
    $job = new SyncExchangeRates;

    $job->handle(app(FrankfurterClient::class));
    $job->handle(app(FrankfurterClient::class));

    Http::assertSentCount(2);
    $this->assertDatabaseCount('exchange_rates', 2);
    $this->assertDatabaseHas('exchange_rates', [
        'base_currency_code' => 'USD',
        'quote_currency_code' => 'MVR',
        'rate' => 16,
    ]);
});

it('does not store partial rates when the response omits an active currency', function () {
    Currency::factory()->usd()->create();
    Currency::factory()->mvr()->create();
    Http::preventStrayRequests();
    Http::fake([
        'api.frankfurter.dev/v2/rates*' => Http::response([
            ['date' => '2026-09-18', 'base' => 'EUR', 'quote' => 'USD', 'rate' => 2],
        ]),
    ]);

    expect(fn () => (new SyncExchangeRates)->handle(app(FrankfurterClient::class)))
        ->toThrow(UnexpectedValueException::class, 'MVR');
    Http::assertSentCount(1);
    $this->assertDatabaseCount('exchange_rates', 0);
});
