<?php

use App\Services\MoneyConverter;

it('converts money between currencies using minor units', function () {
    $converted = (new MoneyConverter)->convert(1000, 100, 100, '15.420000000000');

    expect($converted)->toBe(15420);
});

it('supports currencies with different minor unit factors', function () {
    $converted = (new MoneyConverter)->convert(1234, 1000, 100, '2');

    expect($converted)->toBe(247);
});

it('rounds half up at the target minor unit', function () {
    $converted = (new MoneyConverter)->convert(1, 100, 100, '0.5');

    expect($converted)->toBe(1);
});

it('rejects non-positive rates', function () {
    (new MoneyConverter)->convert(100, 100, 100, '0');
})->throws(InvalidArgumentException::class);
