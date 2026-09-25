<?php

use App\ConnectedAccountProvider;
use App\Jobs\RevokeAppleToken;
use App\Services\Social\AppleIdentityTokenVerifier;
use App\Services\Social\AppleTokenService;
use App\Services\Social\VerifiedSocialIdentity;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

it('revokes the encrypted Apple token and removes it after success', function () {
    Http::preventStrayRequests();
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($key, $pem);
    config(['ovezi.social.apple_team_id' => 'TEAM', 'ovezi.social.apple_key_id' => 'KEY', 'ovezi.social.apple_private_key' => $pem]);
    Http::fake(['https://appleid.apple.com/auth/revoke' => Http::response('', 200)]);
    $id = DB::table('apple_token_revocations')->insertGetId(['token' => Crypt::encryptString('refresh-token'), 'client_id' => 'com.ovezi.app', 'created_at' => now()]);
    (new RevokeAppleToken($id))->handle(app(AppleTokenService::class));
    Http::assertSent(fn ($request) => $request['token'] === 'refresh-token' && $request['client_id'] === 'com.ovezi.app' && $request['token_type_hint'] === 'refresh_token');
    $this->assertDatabaseMissing('apple_token_revocations', ['id' => $id]);
});

it('retains the encrypted token for retry when Apple is unavailable', function () {
    Http::preventStrayRequests();
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($key, $pem);
    config(['ovezi.social.apple_team_id' => 'TEAM', 'ovezi.social.apple_key_id' => 'KEY', 'ovezi.social.apple_private_key' => $pem]);
    Http::fake(['https://appleid.apple.com/auth/revoke' => Http::response('', 503)]);
    $id = DB::table('apple_token_revocations')->insertGetId(['token' => Crypt::encryptString('refresh-token'), 'client_id' => 'com.ovezi.app', 'created_at' => now()]);
    expect(fn () => (new RevokeAppleToken($id))->handle(app(AppleTokenService::class)))->toThrow(RuntimeException::class);
    $this->assertDatabaseHas('apple_token_revocations', ['id' => $id]);
    Http::assertSentCount(1);
});

it('exchanges Apple authorization codes only for the confirmed identity', function (bool $matches) {
    Http::preventStrayRequests();
    $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
    openssl_pkey_export($key, $pem);
    config(['ovezi.social.apple_client_id' => 'com.ovezi.app', 'ovezi.social.apple_team_id' => 'TEAM', 'ovezi.social.apple_key_id' => 'KEY', 'ovezi.social.apple_private_key' => $pem]);
    $identity = new VerifiedSocialIdentity(ConnectedAccountProvider::Apple, 'confirmed-subject', null, true, null, null, now()->timestamp);
    $returned = new VerifiedSocialIdentity(ConnectedAccountProvider::Apple, $matches ? 'confirmed-subject' : 'another-subject', null, true, null, null, now()->timestamp);
    Http::fake(['https://appleid.apple.com/auth/token' => Http::response(['id_token' => 'signed-token', 'refresh_token' => 'refresh-token'])]);
    $this->mock(AppleIdentityTokenVerifier::class)->shouldReceive('verify')->once()->with('signed-token', 'nonce', null)->andReturn($returned);
    $exchange = fn () => app(AppleTokenService::class)->exchange('single-use-code', 'nonce', $identity);
    if ($matches) {
        expect($exchange())->toBe(['refresh_token' => 'refresh-token', 'client_id' => 'com.ovezi.app']);
    } else {
        expect($exchange)->toThrow(ValidationException::class);
    }
    Http::assertSent(fn ($request) => $request['code'] === 'single-use-code' && $request['grant_type'] === 'authorization_code' && $request['client_id'] === 'com.ovezi.app');
})->with([true, false]);
