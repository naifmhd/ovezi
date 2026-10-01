<?php

use App\ConnectedAccountProvider;
use App\Exceptions\SocialProviderUnavailable;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\AppleTokenService;
use App\Services\Social\SocialIdentityVerifier;
use App\Services\Social\VerifiedSocialIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;

it('returns 401 without an authenticated session', function () {
    $this->postJson('/api/v1/auth/social-accounts', [])->assertUnauthorized();

    $this->assertDatabaseCount('social_accounts', 0);
});

it('connects a different Google email without changing the signed-in profile or creating a session', function () {
    $this->freezeTime();
    $user = User::factory()->unverified()->create(['password' => 'password-value']);
    $other = User::factory()->create(['email' => 'different@example.com']);
    $original = $user->fresh()->getAttributes();
    $token = $user->createToken('Phone')->plainTextToken;
    $identity = new VerifiedSocialIdentity(ConnectedAccountProvider::Google, 'google-subject', $other->email, true, 'Google Name', null, now()->timestamp);
    $this->mock(SocialIdentityVerifier::class, function (MockInterface $mock) use ($identity): void {
        $mock->shouldReceive('verify')->once()->with(ConnectedAccountProvider::Google, 'google-token', null, null)->andReturn($identity);
    });

    $this->withToken($token)->postJson('/api/v1/auth/social-accounts', [
        'provider' => 'google', 'id_token' => 'google-token', 'current_password' => 'password-value',
        'user_id' => $other->id, 'email' => $other->email,
    ])->assertOk()->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email)->assertJsonPath('data.connected_providers', ['google'])
        ->assertJsonMissingPath('data.token');

    expect($user->fresh()->getAttributes())->toBe($original);
    $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'google-subject', 'provider_email' => $other->email]);
    $this->assertDatabaseCount('users', 2);
    $this->assertDatabaseCount('personal_access_tokens', 1);
});

it('returns 422 for a missing or incorrect Ovezi password before verifying the provider', function (?string $password) {
    $user = User::factory()->create(['password' => 'password-value']);
    $this->mock(SocialIdentityVerifier::class, fn (MockInterface $mock) => $mock->shouldNotReceive('verify'));

    $response = $this->withToken($user->createToken('Phone')->plainTextToken)->postJson('/api/v1/auth/social-accounts', [
        'provider' => 'google', 'id_token' => 'google-token', 'current_password' => $password,
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

    if ($password !== null) {
        $response->assertJsonPath('errors.current_password.0', 'The current password is incorrect.');
    }
    $this->assertDatabaseCount('social_accounts', 0);
})->with(['missing' => null, 'incorrect' => 'wrong-password']);

it('returns 422 for missing provider proof or invalid provider input', function (array $payload, array $fields) {
    $user = User::factory()->create(['password' => null]);
    $this->mock(SocialIdentityVerifier::class, fn (MockInterface $mock) => $mock->shouldNotReceive('verify'));

    $this->withToken($user->createToken('Phone')->plainTextToken)->postJson('/api/v1/auth/social-accounts', $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($fields);

    $this->assertDatabaseCount('social_accounts', 0);
})->with([
    'empty' => [[], ['provider', 'id_token']],
    'unknown provider' => [['provider' => 'facebook', 'id_token' => 'token'], ['provider']],
    'Apple missing confirmation' => [['provider' => 'apple', 'id_token' => 'token'], ['nonce', 'authorization_code']],
    'Apple short nonce' => [['provider' => 'apple', 'id_token' => 'token', 'nonce' => 'short', 'authorization_code' => 'code'], ['nonce']],
]);

it('returns 422 when the provider rejects the identity token', function () {
    $user = User::factory()->create(['password' => null]);
    $this->mock(SocialIdentityVerifier::class, function (MockInterface $mock): void {
        $mock->shouldReceive('verify')->once()->andThrow(ValidationException::withMessages(['id_token' => 'Invalid identity.']));
    });

    $this->withToken($user->createToken('Phone')->plainTextToken)->postJson('/api/v1/auth/social-accounts', [
        'provider' => 'google', 'id_token' => 'invalid-token',
    ])->assertUnprocessable()->assertJsonValidationErrors('id_token');

    $this->assertDatabaseCount('social_accounts', 0);
});

it('returns 422 when provider proof is stale or lacks a valid issue time', function (?int $offset) {
    $this->freezeTime();
    $user = User::factory()->create(['password' => null]);
    $identity = new VerifiedSocialIdentity(ConnectedAccountProvider::Google, 'subject', 'google@example.com', true, null, null, $offset === null ? null : now()->timestamp + $offset);
    $this->mock(SocialIdentityVerifier::class, fn (MockInterface $mock) => $mock->shouldReceive('verify')->once()->andReturn($identity));

    $this->withToken($user->createToken('Phone')->plainTextToken)->postJson('/api/v1/auth/social-accounts', [
        'provider' => 'google', 'id_token' => 'token',
    ])->assertUnprocessable()->assertJsonPath('errors.id_token.0', 'Please sign in with the provider again to connect your account.');

    $this->assertDatabaseCount('social_accounts', 0);
})->with(['missing time' => null, 'expired proof' => -301, 'future proof' => 61]);

it('returns 422 rather than taking an identity from another account', function (bool $deleted) {
    $this->freezeTime();
    $user = User::factory()->create(['password' => null]);
    $owner = User::factory()->create();
    $account = SocialAccount::factory()->for($owner)->create(['provider' => 'google', 'provider_user_id' => 'claimed-subject']);
    if ($deleted) {
        $owner->delete();
    }
    $identity = new VerifiedSocialIdentity(ConnectedAccountProvider::Google, 'claimed-subject', $user->email, true, null, null, now()->timestamp);
    $this->mock(SocialIdentityVerifier::class, fn (MockInterface $mock) => $mock->shouldReceive('verify')->once()->andReturn($identity));

    $this->withToken($user->createToken('Phone')->plainTextToken)->postJson('/api/v1/auth/social-accounts', [
        'provider' => 'google', 'id_token' => 'token',
    ])->assertUnprocessable()->assertJsonPath('errors.provider.0', 'This provider account is already connected to another Ovezi account.');

    expect($account->fresh()->user_id)->toBe($owner->id);
    $this->assertDatabaseCount('social_accounts', 1);
})->with(['active owner' => false, 'deleted owner' => true]);

it('returns 422 instead of replacing an existing provider identity', function () {
    $this->freezeTime();
    $user = User::factory()->create(['password' => null]);
    $account = SocialAccount::factory()->for($user)->create(['provider' => 'google', 'provider_user_id' => 'original-subject']);
    $identity = new VerifiedSocialIdentity(ConnectedAccountProvider::Google, 'different-subject', $user->email, true, null, null, now()->timestamp);
    $this->mock(SocialIdentityVerifier::class, fn (MockInterface $mock) => $mock->shouldReceive('verify')->once()->andReturn($identity));

    $this->withToken($user->createToken('Phone')->plainTextToken)->postJson('/api/v1/auth/social-accounts', [
        'provider' => 'google', 'id_token' => 'token',
    ])->assertUnprocessable()->assertJsonValidationErrors('provider');

    expect($account->fresh()->provider_user_id)->toBe('original-subject');
    $this->assertDatabaseCount('social_accounts', 1);
});

it('accepts a retry for the same identity without duplicating the connection', function () {
    $this->freezeTime();
    $user = User::factory()->create(['password' => null]);
    $account = SocialAccount::factory()->for($user)->create(['provider' => 'google', 'provider_user_id' => 'same-subject']);
    $identity = new VerifiedSocialIdentity(ConnectedAccountProvider::Google, 'same-subject', $user->email, true, null, null, now()->timestamp);
    $this->mock(SocialIdentityVerifier::class, fn (MockInterface $mock) => $mock->shouldReceive('verify')->once()->andReturn($identity));

    $this->withToken($user->createToken('Phone')->plainTextToken)->postJson('/api/v1/auth/social-accounts', [
        'provider' => 'google', 'id_token' => 'token',
    ])->assertOk()->assertJsonPath('data.connected_providers', ['google']);

    $this->assertModelExists($account);
    $this->assertDatabaseCount('social_accounts', 1);
});

it('connects Apple to a passwordless account and stores revocation credentials encrypted', function (?string $email) {
    $this->freezeTime();
    $user = User::factory()->create(['password' => null]);
    SocialAccount::factory()->for($user)->create(['provider' => 'google']);
    $identity = new VerifiedSocialIdentity(ConnectedAccountProvider::Apple, 'apple-subject', $email, $email !== null, null, null, now()->timestamp);
    $nonce = 'fresh-apple-nonce-value';
    $this->mock(SocialIdentityVerifier::class, fn (MockInterface $mock) => $mock->shouldReceive('verify')->once()->with(ConnectedAccountProvider::Apple, 'apple-token', $nonce, null)->andReturn($identity));
    $this->mock(AppleTokenService::class, fn (MockInterface $mock) => $mock->shouldReceive('exchange')->once()->with('apple-code', $nonce, $identity)->andReturn(['refresh_token' => 'secret-refresh', 'client_id' => 'test-client']));

    $this->withToken($user->createToken('Phone')->plainTextToken)->postJson('/api/v1/auth/social-accounts', [
        'provider' => 'apple', 'id_token' => 'apple-token', 'nonce' => $nonce, 'authorization_code' => 'apple-code',
    ])->assertOk()->assertJsonPath('data.connected_providers', ['apple', 'google'])
        ->assertJsonPath('data.email', $user->email)->assertJsonMissingPath('data.refresh_token');

    $account = $user->socialAccounts()->where('provider', 'apple')->firstOrFail();
    expect($account->refresh_token)->toBe('secret-refresh');
    expect(DB::table('social_accounts')->where('id', $account->id)->value('refresh_token'))->not->toBe('secret-refresh');
    expect($account->provider_email)->toBe($email);
    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('personal_access_tokens', 1);
})->with(['private relay' => 'private@privaterelay.appleid.com', 'returning Apple identity without email' => null]);

it('does not connect Apple when code exchange is unavailable', function () {
    $this->freezeTime();
    $user = User::factory()->create(['password' => null]);
    $identity = new VerifiedSocialIdentity(ConnectedAccountProvider::Apple, 'subject', null, false, null, null, now()->timestamp);
    $this->mock(SocialIdentityVerifier::class, fn (MockInterface $mock) => $mock->shouldReceive('verify')->once()->andReturn($identity));
    $this->mock(AppleTokenService::class, fn (MockInterface $mock) => $mock->shouldReceive('exchange')->once()->andThrow(new SocialProviderUnavailable('Unavailable')));

    $this->withToken($user->createToken('Phone')->plainTextToken)->postJson('/api/v1/auth/social-accounts', [
        'provider' => 'apple', 'id_token' => 'token', 'nonce' => 'fresh-apple-nonce-value', 'authorization_code' => 'code',
    ])->assertServiceUnavailable();

    $this->assertDatabaseCount('social_accounts', 0);
});

it('returns 422 if the Ovezi password changes during provider verification', function () {
    $this->freezeTime();
    $user = User::factory()->create(['password' => 'original-password']);
    $identity = new VerifiedSocialIdentity(ConnectedAccountProvider::Google, 'subject', $user->email, true, null, null, now()->timestamp);
    $this->mock(SocialIdentityVerifier::class, function (MockInterface $mock) use ($identity, $user): void {
        $mock->shouldReceive('verify')->once()->andReturnUsing(function () use ($identity, $user): VerifiedSocialIdentity {
            $user->update(['password' => 'changed-password']);

            return $identity;
        });
    });

    $this->withToken($user->createToken('Phone')->plainTextToken)->postJson('/api/v1/auth/social-accounts', [
        'provider' => 'google', 'id_token' => 'token', 'current_password' => 'original-password',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $this->assertDatabaseCount('social_accounts', 0);
});

it('uses the explicitly connected identity for subsequent sign-in despite a different account email', function () {
    $this->freezeTime();
    $user = User::factory()->create(['password' => 'password-value']);
    $identity = new VerifiedSocialIdentity(ConnectedAccountProvider::Google, 'linked-subject', 'different@example.com', true, null, null, now()->timestamp);
    $this->mock(SocialIdentityVerifier::class, fn (MockInterface $mock) => $mock->shouldReceive('verify')->twice()->andReturn($identity));
    $this->withToken($user->createToken('Phone')->plainTextToken)->postJson('/api/v1/auth/social-accounts', [
        'provider' => 'google', 'id_token' => 'token', 'current_password' => 'password-value',
    ])->assertOk();

    $this->postJson('/api/v1/auth/social', ['provider' => 'google', 'id_token' => 'fresh-token', 'device_name' => 'Next phone'])
        ->assertOk()->assertJsonPath('data.user.id', $user->id)->assertJsonPath('data.user.email', $user->email);

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseCount('social_accounts', 1);
});
