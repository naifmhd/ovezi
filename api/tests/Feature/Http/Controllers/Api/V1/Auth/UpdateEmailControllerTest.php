<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

it('changes the email, requires reverification, and revokes other sessions', function () {
    Notification::fake();
    $user = User::factory()->create([
        'email' => 'old@example.com',
        'password' => 'current-password-value',
    ]);
    $currentToken = $user->createToken('Current phone');
    $otherToken = $user->createToken('Tablet');

    $response = $this->withToken($currentToken->plainTextToken)->putJson('/api/v1/auth/email', [
        'current_password' => 'current-password-value',
        'email' => ' NEW@Example.com ',
        'email_confirmation' => 'new@example.com',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.email', 'new@example.com')
        ->assertJsonPath('data.email_verified_at', null);
    $user->refresh();
    expect($user->email)->toBe('new@example.com')
        ->and($user->email_verified_at)->toBeNull();
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $currentToken->accessToken->id]);
    $this->assertDatabaseMissing('personal_access_tokens', ['id' => $otherToken->accessToken->id]);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('rejects an incorrect password, duplicate email, or unchanged email', function () {
    $otherUser = User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create([
        'email' => 'current@example.com',
        'password' => 'current-password-value',
    ]);
    $token = $user->createToken('User phone');

    $this->withToken($token->plainTextToken)->putJson('/api/v1/auth/email', [
        'current_password' => 'incorrect-password',
        'email' => 'new@example.com',
        'email_confirmation' => 'new@example.com',
    ])->assertUnprocessable()->assertJsonValidationErrors('current_password');

    $this->withToken($token->plainTextToken)->putJson('/api/v1/auth/email', [
        'current_password' => 'current-password-value',
        'email' => $otherUser->email,
        'email_confirmation' => $otherUser->email,
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    $this->withToken($token->plainTextToken)->putJson('/api/v1/auth/email', [
        'current_password' => 'current-password-value',
        'email' => $user->email,
        'email_confirmation' => $user->email,
    ])->assertUnprocessable()->assertJsonValidationErrors('email');
});
