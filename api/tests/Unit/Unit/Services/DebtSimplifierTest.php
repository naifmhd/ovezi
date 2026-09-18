<?php

use App\Services\DebtSimplifier;

it('produces deterministic settlement suggestions that clear all balances', function () {
    $transactions = (new DebtSimplifier)->simplify([
        'user:1' => 700,
        'user:2' => 300,
        'user:3' => -600,
        'placeholder:1' => -400,
        'user:4' => 0,
    ]);

    expect($transactions)->toBe([
        ['from' => 'user:3', 'to' => 'user:1', 'amount_minor' => 600],
        ['from' => 'placeholder:1', 'to' => 'user:1', 'amount_minor' => 100],
        ['from' => 'placeholder:1', 'to' => 'user:2', 'amount_minor' => 300],
    ]);
});

it('returns no suggestions when every balance is zero', function () {
    expect((new DebtSimplifier)->simplify(['user:1' => 0]))->toBe([]);
});
