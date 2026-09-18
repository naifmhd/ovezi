<?php

use App\Models\Currency;
use App\Models\User;

it('lists active currencies in code order', function () {
    $user = User::factory()->create();
    Currency::factory()->create(['code' => 'USD', 'name' => 'United States Dollar']);
    Currency::factory()->create(['code' => 'MVR', 'name' => 'Maldivian Rufiyaa']);
    Currency::factory()->create(['code' => 'EUR', 'name' => 'Euro', 'is_active' => false]);

    $this->withToken($user->createToken('User phone')->plainTextToken)
        ->getJson('/api/v1/currencies')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.code', 'MVR')
        ->assertJsonPath('data.1.code', 'USD')
        ->assertJsonMissing(['code' => 'EUR']);
});

it('requires authentication to list currencies', function () {
    $this->getJson('/api/v1/currencies')->assertUnauthorized();
});
