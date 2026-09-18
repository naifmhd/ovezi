<?php

use App\Exceptions\InvalidSplit;
use App\Services\SplitCalculator;
use App\SplitType;

it('assigns an equal split remainder to the payer', function () {
    $allocations = (new SplitCalculator)->calculate(
        100,
        SplitType::Equal,
        ['user:1' => 1, 'user:2' => 1, 'user:3' => 1],
        'user:2',
    );

    expect($allocations)->toBe([
        'user:1' => 33,
        'user:2' => 34,
        'user:3' => 33,
    ]);
});

it('calculates percentage splits from basis points', function () {
    $allocations = (new SplitCalculator)->calculate(
        101,
        SplitType::Percentage,
        ['user:1' => 5000, 'user:2' => 3000, 'user:3' => 2000],
        'user:1',
    );

    expect($allocations)->toBe([
        'user:1' => 51,
        'user:2' => 30,
        'user:3' => 20,
    ]);
});

it('calculates share splits without floating point arithmetic', function () {
    $allocations = (new SplitCalculator)->calculate(
        PHP_INT_MAX - 10,
        SplitType::Shares,
        ['user:1' => 2, 'user:2' => 1],
        'user:1',
    );

    expect(array_sum($allocations))->toBe(PHP_INT_MAX - 10)
        ->and($allocations['user:1'])->toBeGreaterThan($allocations['user:2']);
});

it('accepts exact splits that equal the expense amount', function () {
    $allocations = (new SplitCalculator)->calculate(
        100,
        SplitType::Exact,
        ['user:1' => 75, 'user:2' => 25],
        'user:1',
    );

    expect($allocations)->toBe(['user:1' => 75, 'user:2' => 25]);
});

it('rejects invalid split inputs', function (SplitType $type, array $values, string $payer) {
    (new SplitCalculator)->calculate(100, $type, $values, $payer);
})->with([
    'exact total mismatch' => [SplitType::Exact, ['user:1' => 99], 'user:1'],
    'percentage total mismatch' => [SplitType::Percentage, ['user:1' => 9999], 'user:1'],
    'zero shares' => [SplitType::Shares, ['user:1' => 0], 'user:1'],
    'payer omitted' => [SplitType::Equal, ['user:2' => 1], 'user:1'],
])->throws(InvalidSplit::class);
