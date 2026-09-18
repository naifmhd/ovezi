<?php

namespace App\Services;

use App\ExchangeRateSource;
use Carbon\CarbonImmutable;

final readonly class ResolvedExchangeRate
{
    public function __construct(
        public ?string $rate,
        public ExchangeRateSource $source,
        public ?CarbonImmutable $effectiveDate,
    ) {}
}
