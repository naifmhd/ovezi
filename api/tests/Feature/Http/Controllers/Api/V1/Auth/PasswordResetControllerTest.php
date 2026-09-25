<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

it('sends a reset notification using an HTTPS app link', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'user@example.com']);

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => ' USER@EXAMPLE.COM ',
    ])->assertAccepted()
        ->assertJsonPath('message', 'If an account matches that email, a password reset link has been sent.');

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, route('app.reset').'?token=')
            && str_contains($url, 'email=user%40example.com');
    });
});

it('does not disclose whether a reset email exists', function () {
    Notification::fake();

    $this->postJson('/api/v1/auth/forgot-password', [
        'email' => 'missing@example.com',
    ])->assertAccepted()
        ->assertJsonPath('message', 'If an account matches that email, a password reset link has been sent.');

    Notification::assertNothingSent();
});

it('resets the password and revokes every existing session', function () {
    $user = User::factory()->create(['email' => 'user@example.com', 'password' => 'old-password-value']);
    $user->createToken('Phone');
    $user->createToken('Tablet');
    $resetToken = Password::createToken($user);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => $resetToken,
        'email' => $user->email,
        'password' => 'new-password-value',
        'password_confirmation' => 'new-password-value',
    ])->assertNoContent();

    expect(Hash::check('new-password-value', $user->fresh()->password))->toBeTrue();
    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('rejects an invalid reset token', function () {
    $user = User::factory()->create(['email' => 'user@example.com']);

    $this->postJson('/api/v1/auth/reset-password', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'new-password-value',
        'password_confirmation' => 'new-password-value',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});
