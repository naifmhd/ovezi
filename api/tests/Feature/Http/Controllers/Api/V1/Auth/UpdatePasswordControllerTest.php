<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('changes the password, keeps the current token, and revokes other sessions', function () {
    $user = User::factory()->create(['password' => 'old-password-value']);
    $currentToken = $user->createToken('Current phone');
    $otherToken = $user->createToken('Tablet');

    $this->withToken($currentToken->plainTextToken)->putJson('/api/v1/auth/password', [
        'current_password' => 'old-password-value',
        'password' => 'new-password-value',
        'password_confirmation' => 'new-password-value',
    ])->assertNoContent();

    expect(Hash::check('new-password-value', $user->fresh()->password))->toBeTrue();
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentToken->accessToken->id]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherToken->accessToken->id]);

    app('auth')->forgetGuards();
    $this->withToken($currentToken->plainTextToken)->getJson('/api/v1/me')->assertOk();
    app('auth')->forgetGuards();
    $this->withToken($otherToken->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
});

it('rejects an incorrect current password without changing credentials', function () {
    $user = User::factory()->create(['password' => 'old-password-value']);
    $token = $user->createToken('Current phone');

    $this->withToken($token->plainTextToken)->putJson('/api/v1/auth/password', [
        'current_password' => 'incorrect-password',
        'password' => 'new-password-value',
        'password_confirmation' => 'new-password-value',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect(Hash::check('old-password-value', $user->fresh()->password))->toBeTrue();
});

it('requires confirmation and a different new password', function () {
    $user = User::factory()->create(['password' => 'current-password-value']);
    $token = $user->createToken('Current phone');

    $this->withToken($token->plainTextToken)->putJson('/api/v1/auth/password', [
        'current_password' => 'current-password-value',
        'password' => 'current-password-value',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('password');
});

it('requires authentication', function () {
    $this->putJson('/api/v1/auth/password')->assertUnauthorized();
});
