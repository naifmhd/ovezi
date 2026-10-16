<?php

use App\Services\ReportingSplitAllocator;

it('allocates reporting amounts proportionally and assigns the remainder to the payer', function () {
    $allocations = (new ReportingSplitAllocator)->allocate(
        101,
        ['user:1' => 50, 'user:2' => 30, 'placeholder:1' => 20],
        'user:2',
    );

    expect($allocations)->toBe([
        'user:1' => 50,
        'user:2' => 31,
        'placeholder:1' => 20,
    ])->and(array_sum($allocations))->toBe(101);
});

it('uses integer math for very large amounts', function () {
    $allocations = (new ReportingSplitAllocator)->allocate(
        PHP_INT_MAX - 100,
        ['user:1' => PHP_INT_MAX - 200, 'user:2' => 100],
        'user:1',
    );

    expect(array_sum($allocations))->toBe(PHP_INT_MAX - 100);
});

it('requires a positive base total', function (array $baseAllocations, string $payer) {
    (new ReportingSplitAllocator)->allocate(100, $baseAllocations, $payer);
})->with([
    'zero total' => [['user:1' => 0], 'user:1'],
])->throws(InvalidArgumentException::class);

it('assigns the reporting remainder to a beneficiary when the payer is excluded', function () {
    $allocations = (new ReportingSplitAllocator)->allocate(
        101,
        ['user:1' => 50, 'user:2' => 50],
        'user:3',
    );

    expect($allocations)->toBe([
        'user:1' => 51,
        'user:2' => 50,
    ])->and(array_sum($allocations))->toBe(101);
});
