<?php

use App\ConnectedAccountProvider;
use App\Models\SocialAccount;
use App\Models\User;

it('disconnects a provider when the account has a password', function () {
    $user = User::factory()->create(['password' => 'password-value']);
    SocialAccount::factory()->for($user)->create([
        'provider' => ConnectedAccountProvider::Google,
    ]);
    $token = $user->createToken('User phone');

    $this->withToken($token->plainTextToken)
        ->deleteJson('/api/v1/auth/social-accounts/google')
        ->assertNoContent();

    $this->assertDatabaseCount('social_accounts', 0);
});

it('disconnects one provider when another provider remains', function () {
    $user = User::factory()->create(['password' => null]);
    SocialAccount::factory()->for($user)->create([
        'provider' => ConnectedAccountProvider::Google,
    ]);
    SocialAccount::factory()->for($user)->create([
        'provider' => ConnectedAccountProvider::Apple,
    ]);
    $token = $user->createToken('User phone');

    $this->withToken($token->plainTextToken)
        ->deleteJson('/api/v1/auth/social-accounts/google')
        ->assertNoContent();

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => ConnectedAccountProvider::Apple->value,
    ]);
});

it('does not disconnect the only login method', function () {
    $user = User::factory()->create(['password' => null]);
    SocialAccount::factory()->for($user)->create([
        'provider' => ConnectedAccountProvider::Google,
    ]);
    $token = $user->createToken('User phone');

    $this->withToken($token->plainTextToken)
        ->deleteJson('/api/v1/auth/social-accounts/google')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('provider');
});

it('returns 404 for a provider that is not connected', function () {
    $user = User::factory()->create();
    $token = $user->createToken('User phone');

    $this->withToken($token->plainTextToken)
        ->deleteJson('/api/v1/auth/social-accounts/google')
        ->assertNotFound();
});
