<?php

use App\Models\User;

it('returns a mobile access token for valid credentials', function () {
    $user = User::factory()->create([
        'email' => 'naif@example.com',
        'password' => 'correct-horse-battery-staple',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => ' NAIF@EXAMPLE.COM ',
        'password' => 'correct-horse-battery-staple',
        'device_name' => 'Naif iPhone',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.email', 'naif@example.com')
        ->assertJsonStructure(['data' => ['user', 'token']]);

    expect($user->tokens()->sole()->name)->toBe('Naif iPhone');
});

it('returns 422 when the credentials are incorrect', function () {
    User::factory()->create([
        'email' => 'naif@example.com',
        'password' => 'correct-horse-battery-staple',
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'naif@example.com',
        'password' => 'incorrect-password',
        'device_name' => 'Naif iPhone',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email')
        ->assertJsonPath('errors.email.0', 'The provided credentials are incorrect.');

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('revokes only the token used to log out', function () {
    $user = User::factory()->create();
    $currentToken = $user->createToken('Current phone');
    $otherToken = $user->createToken('Other phone');

    $response = $this
        ->withToken($currentToken->plainTextToken)
        ->deleteJson('/api/v1/auth/session');

    $response->assertNoContent();

    $this->assertDatabaseMissing('personal_access_tokens', [
        'id' => $currentToken->accessToken->id,
    ]);
    $this->assertDatabaseHas('personal_access_tokens', [
        'id' => $otherToken->accessToken->id,
    ]);
});

it('returns 401 when logging out without an access token', function () {
    $this->deleteJson('/api/v1/auth/session')->assertUnauthorized();
});
