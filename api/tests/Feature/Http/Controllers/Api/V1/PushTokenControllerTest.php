<?php

use App\Models\PushToken;
use App\Models\User;

it('returns 401 when registering a push token without authentication', function () {
    $this->postJson('/api/v1/push-tokens', [
        'expo_push_token' => 'ExponentPushToken[valid_token_1234567890]',
        'platform' => 'ios',
    ])->assertUnauthorized();
});

it('returns 422 for an invalid push token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Phone');

    $this->withToken($token->plainTextToken)->postJson('/api/v1/push-tokens', [
        'expo_push_token' => 'not-a-push-token',
        'platform' => 'desktop',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['expo_push_token', 'platform']);

    $this->assertDatabaseCount('push_tokens', 0);
});

it('registers a device and safely transfers the same device after another user signs in', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();
    $secondToken = $secondUser->createToken('Second phone');
    $pushToken = 'ExponentPushToken[device_token_1234567890]';
    PushToken::factory()->for($firstUser)->create([
        'expo_push_token' => $pushToken,
        'platform' => 'ios',
        'device_name' => 'iPhone',
    ]);

    $this->withToken($secondToken->plainTextToken)->postJson('/api/v1/push-tokens', [
        'expo_push_token' => $pushToken,
        'platform' => 'android',
        'device_name' => 'Pixel',
        'user_id' => $firstUser->id,
        'revoked_at' => now()->toISOString(),
    ])
        ->assertOk()
        ->assertJsonPath('data.platform', 'android')
        ->assertJsonMissingPath('data.expo_push_token');

    $this->assertDatabaseCount('push_tokens', 1);
    $this->assertDatabaseHas('push_tokens', [
        'user_id' => $secondUser->id,
        'expo_push_token' => $pushToken,
        'device_name' => 'Pixel',
        'revoked_at' => null,
    ]);
});

it('revokes only the authenticated users matching device', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = $user->createToken('Phone');
    $otherPushToken = PushToken::factory()->for($otherUser)->create();

    $this->withToken($token->plainTextToken)->deleteJson('/api/v1/push-tokens', [
        'expo_push_token' => $otherPushToken->expo_push_token,
    ])->assertNoContent();

    expect($otherPushToken->fresh()->revoked_at)->toBeNull();
});
