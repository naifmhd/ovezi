<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

it('sends a mobile deep-link verification notification', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $token = $user->createToken('User phone');

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertNoContent();

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, 'ovezi://auth/verify-email?verification_url=')
            && str_contains(rawurldecode($url), '/api/v1/auth/email/verify/');
    });
});

it('verifies an email through a valid signed API URL', function () {
    $user = User::factory()->unverified()->create();
    $verificationUrl = URL::temporarySignedRoute(
        'api.v1.auth.email.verify',
        now()->addMinutes(10),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->getJson($verificationUrl)->assertNoContent();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects tampered or incorrect verification links', function () {
    $user = User::factory()->unverified()->create();
    $validUrl = URL::temporarySignedRoute(
        'api.v1.auth.email.verify',
        now()->addMinutes(10),
        ['id' => $user->id, 'hash' => sha1($user->email)],
    );

    $this->getJson($validUrl.'&tampered=1')->assertForbidden();

    $wrongHashUrl = URL::temporarySignedRoute(
        'api.v1.auth.email.verify',
        now()->addMinutes(10),
        ['id' => $user->id, 'hash' => sha1('wrong@example.com')],
    );
    $this->getJson($wrongHashUrl)->assertForbidden();
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('does not send another notification to an already verified user', function () {
    Notification::fake();
    $user = User::factory()->create();
    $token = $user->createToken('User phone');

    $this->withToken($token->plainTextToken)
        ->postJson('/api/v1/auth/email/verification-notification')
        ->assertNoContent();

    Notification::assertNothingSent();
});
