<?php

use App\Models\User;

it('returns the authenticated profile', function () {
    $user = User::factory()->create([
        'name' => 'Naif Ahmed',
        'email' => 'naif@example.com',
    ]);
    $token = $user->createToken('Naif iPhone');

    $response = $this
        ->withToken($token->plainTextToken)
        ->getJson('/api/v1/me');

    $response
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.name', 'Naif Ahmed')
        ->assertJsonPath('data.email', 'naif@example.com')
        ->assertJsonMissingPath('data.password');
});

it('returns 401 when no access token is provided', function () {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});
