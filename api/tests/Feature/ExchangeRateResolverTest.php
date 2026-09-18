<?php

use App\ExchangeRateSource;
use App\Models\Currency;
use App\Models\ExchangeRate;
use App\Models\Group;
use App\Models\GroupCurrencyRate;
use App\Models\User;
use App\Services\ExchangeRateResolver;
use Carbon\CarbonImmutable;

beforeEach(function () {
    Currency::factory()->usd()->create();
    Currency::factory()->mvr()->create();
});

it('does not require a rate when currencies match', function () {
    $resolved = app(ExchangeRateResolver::class)->resolve(
        'MVR',
        'MVR',
        CarbonImmutable::parse('2026-09-18'),
    );

    expect($resolved->rate)->toBeNull()
        ->and($resolved->source)->toBe(ExchangeRateSource::SameCurrency)
        ->and($resolved->effectiveDate)->toBeNull();
});

it('prioritizes an expense rate over group and provider rates', function () {
    $owner = User::factory()->create(['default_currency_code' => 'MVR']);
    $group = Group::factory()->create([
        'created_by' => $owner->id,
        'reporting_currency_code' => 'MVR',
    ]);
    GroupCurrencyRate::factory()->create([
        'group_id' => $group->id,
        'base_currency_code' => 'USD',
        'quote_currency_code' => 'MVR',
        'created_by' => $owner->id,
        'rate' => '15.500000000000',
    ]);
    ExchangeRate::factory()->create([
        'base_currency_code' => 'USD',
        'quote_currency_code' => 'MVR',
        'rate' => '15.420000000000',
        'effective_date' => '2026-09-18',
    ]);

    $resolved = app(ExchangeRateResolver::class)->resolve(
        'USD',
        'MVR',
        CarbonImmutable::parse('2026-09-18'),
        $group,
        '15.600000000000',
    );

    expect($resolved->rate)->toBe('15.600000000000')
        ->and($resolved->source)->toBe(ExchangeRateSource::Expense);
});

it('uses a group override before a provider rate', function () {
    $owner = User::factory()->create(['default_currency_code' => 'MVR']);
    $group = Group::factory()->create([
        'created_by' => $owner->id,
        'reporting_currency_code' => 'MVR',
    ]);
    GroupCurrencyRate::factory()->create([
        'group_id' => $group->id,
        'base_currency_code' => 'USD',
        'quote_currency_code' => 'MVR',
        'created_by' => $owner->id,
        'rate' => '15.500000000000',
    ]);

    $resolved = app(ExchangeRateResolver::class)->resolve(
        'USD',
        'MVR',
        CarbonImmutable::parse('2026-09-18'),
        $group,
    );

    expect($resolved->rate)->toBe('15.500000000000')
        ->and($resolved->source)->toBe(ExchangeRateSource::Group);
});

it('uses a provider rate up to seven days old', function () {
    ExchangeRate::factory()->create([
        'base_currency_code' => 'USD',
        'quote_currency_code' => 'MVR',
        'effective_date' => '2026-09-11',
    ]);

    $resolved = app(ExchangeRateResolver::class)->resolve(
        'USD',
        'MVR',
        CarbonImmutable::parse('2026-09-18'),
    );

    expect($resolved->source)->toBe(ExchangeRateSource::Provider)
        ->and($resolved->effectiveDate?->toDateString())->toBe('2026-09-11');
});

it('rejects a provider rate older than seven days', function () {
    ExchangeRate::factory()->create([
        'base_currency_code' => 'USD',
        'quote_currency_code' => 'MVR',
        'effective_date' => '2026-09-10',
    ]);

    app(ExchangeRateResolver::class)->resolve(
        'USD',
        'MVR',
        CarbonImmutable::parse('2026-09-18'),
    );
})->throws(RuntimeException::class);
