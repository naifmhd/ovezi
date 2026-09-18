<?php

use App\Models\User;

it('revokes every access token including the current session', function () {
    $user = User::factory()->create();
    $currentToken = $user->createToken('Current phone');
    $otherToken = $user->createToken('Tablet');

    $this->withToken($currentToken->plainTextToken)
        ->deleteJson('/api/v1/auth/sessions')
        ->assertNoContent();

    $this->assertDatabaseCount('personal_access_tokens', 0);
    app('auth')->forgetGuards();
    $this->withToken($currentToken->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
    app('auth')->forgetGuards();
    $this->withToken($otherToken->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
});

it('requires authentication', function () {
    $this->deleteJson('/api/v1/auth/sessions')->assertUnauthorized();
});
