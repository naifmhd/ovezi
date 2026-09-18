<?php

use App\Models\Currency;
use App\Models\User;

it('updates the profile name and active default currency', function () {
    $mvr = Currency::factory()->mvr()->create();
    $usd = Currency::factory()->usd()->create();
    $user = User::factory()->create(['default_currency_code' => $mvr->code]);
    $token = $user->createToken('User phone');

    $response = $this->withToken($token->plainTextToken)->patchJson('/api/v1/me', [
        'name' => ' Updated Name ',
        'default_currency_code' => 'usd',
        'email' => 'changed@example.com',
        'avatar_path' => '/unsafe/path.jpg',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.default_currency_code', $usd->code)
        ->assertJsonPath('data.email', $user->email);
    $user->refresh();
    expect($user->name)->toBe('Updated Name')
        ->and($user->default_currency_code)->toBe($usd->code)
        ->and($user->email)->not->toBe('changed@example.com')
        ->and($user->avatar_path)->toBeNull();
});

it('requires a supported profile field and an active currency', function () {
    $inactiveCurrency = Currency::factory()->create(['is_active' => false]);
    $user = User::factory()->create();
    $token = $user->createToken('User phone');

    $this->withToken($token->plainTextToken)
        ->patchJson('/api/v1/me', ['email' => 'ignored@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('profile');

    $this->withToken($token->plainTextToken)
        ->patchJson('/api/v1/me', ['default_currency_code' => $inactiveCurrency->code])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('default_currency_code');
});

it('requires authentication', function () {
    $this->patchJson('/api/v1/me', ['name' => 'Changed'])->assertUnauthorized();
});
