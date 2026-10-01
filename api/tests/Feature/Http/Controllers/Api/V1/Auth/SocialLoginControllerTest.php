<?php

use App\ConnectedAccountProvider;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\SocialIdentityVerifier;
use App\Services\Social\VerifiedSocialIdentity;
use Mockery\MockInterface;

it('creates an account from a verified Google identity and imports its profile', function () {
    $identity = new VerifiedSocialIdentity(
        ConnectedAccountProvider::Google,
        'google-user-123',
        'person@example.com',
        true,
        'Person Name',
        'https://example.com/avatar.jpg',
    );
    $this->mock(SocialIdentityVerifier::class, function (MockInterface $mock) use ($identity): void {
        $mock->shouldReceive('verify')->once()->andReturn($identity);
    });

    $response = $this->postJson('/api/v1/auth/social', [
        'provider' => 'google',
        'id_token' => 'provider-token',
        'device_name' => 'iPhone',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.user.name', 'Person Name')
        ->assertJsonPath('data.user.email', 'person@example.com')
        ->assertJsonPath('data.user.avatar_url', 'https://example.com/avatar.jpg')
        ->assertJsonStructure(['data' => ['token']]);
    $user = User::query()->sole();
    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->password)->toBeNull();
    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => ConnectedAccountProvider::Google->value,
        'provider_user_id' => 'google-user-123',
    ]);
});

it('automatically links only a verified provider email to an existing account', function () {
    $user = User::factory()->unverified()->create([
        'name' => 'Existing Name',
        'email' => 'person@example.com',
    ]);
    $identity = new VerifiedSocialIdentity(
        ConnectedAccountProvider::Google,
        'google-user-456',
        $user->email,
        true,
        'Provider Name',
        'https://example.com/provider.jpg',
    );
    $this->mock(SocialIdentityVerifier::class, function (MockInterface $mock) use ($identity): void {
        $mock->shouldReceive('verify')->once()->andReturn($identity);
    });

    $this->postJson('/api/v1/auth/social', [
        'provider' => 'google',
        'id_token' => 'provider-token',
        'device_name' => 'Android phone',
    ])->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.name', 'Existing Name');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider_user_id' => 'google-user-456',
    ]);
});

it('logs in through an existing provider identity without overwriting the user profile', function () {
    $user = User::factory()->create(['name' => 'Custom Name']);
    SocialAccount::factory()->for($user)->create([
        'provider' => ConnectedAccountProvider::Apple,
        'provider_user_id' => 'apple-user-123',
    ]);
    $identity = new VerifiedSocialIdentity(
        ConnectedAccountProvider::Apple,
        'apple-user-123',
        $user->email,
        true,
        'Apple Name',
        null,
    );
    $this->mock(SocialIdentityVerifier::class, function (MockInterface $mock) use ($identity): void {
        $mock->shouldReceive('verify')->once()->andReturn($identity);
    });

    $this->postJson('/api/v1/auth/social', [
        'provider' => 'apple',
        'id_token' => 'provider-token',
        'nonce' => str_repeat('a', 32),
        'device_name' => 'iPhone',
        'name' => 'Apple Name',
    ])->assertOk()
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.user.name', 'Custom Name');
});

it('rejects an unverified email for new account linking', function () {
    $identity = new VerifiedSocialIdentity(
        ConnectedAccountProvider::Google,
        'google-user-unverified',
        'person@example.com',
        false,
        'Person',
        null,
    );
    $this->mock(SocialIdentityVerifier::class, function (MockInterface $mock) use ($identity): void {
        $mock->shouldReceive('verify')->once()->andReturn($identity);
    });

    $this->postJson('/api/v1/auth/social', [
        'provider' => 'google',
        'id_token' => 'provider-token',
        'device_name' => 'Phone',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('id_token');

    $this->assertDatabaseCount('users', 0);
});

it('requires a nonce for Apple and validates supported providers', function () {
    $this->postJson('/api/v1/auth/social', [
        'provider' => 'apple',
        'id_token' => 'provider-token',
        'device_name' => 'iPhone',
    ])->assertUnprocessable()->assertJsonValidationErrors('nonce');

    $this->postJson('/api/v1/auth/social', [
        'provider' => 'facebook',
        'id_token' => 'provider-token',
        'device_name' => 'Phone',
    ])->assertUnprocessable()->assertJsonValidationErrors('provider');
});

it('returns a recoverable 503 without creating a session when Apple server credentials are missing', function () {
    config(['ovezi.social.apple_client_id' => 'com.ovezi.app', 'ovezi.social.apple_team_id' => null, 'ovezi.social.apple_key_id' => null, 'ovezi.social.apple_private_key' => null]);
    $identity = new VerifiedSocialIdentity(ConnectedAccountProvider::Apple, 'apple-subject', 'apple@example.com', true, 'Apple User', null);
    $this->mock(SocialIdentityVerifier::class)->shouldReceive('verify')->once()->andReturn($identity);

    $this->postJson('/api/v1/auth/social', [
        'provider' => 'apple', 'id_token' => 'verified-token', 'nonce' => str_repeat('a', 32),
        'authorization_code' => 'single-use-code', 'device_name' => 'iPhone',
    ])->assertServiceUnavailable()->assertJsonPath('message', 'Apple sign-in is temporarily unavailable. Please try again later or use another sign-in method.');

    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('personal_access_tokens', 0);
});
