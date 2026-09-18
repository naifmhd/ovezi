<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('creates an account and returns a mobile access token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => '  Naif Ahmed  ',
        'email' => '  NAIF@EXAMPLE.COM  ',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
        'device_name' => 'Naif iPhone',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.user.name', 'Naif Ahmed')
        ->assertJsonPath('data.user.email', 'naif@example.com')
        ->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'token']])
        ->assertJsonMissingPath('data.user.password');

    $user = User::query()->where('email', 'naif@example.com')->firstOrFail();

    expect(Hash::check('correct-horse-battery-staple', $user->password))->toBeTrue();
    expect($user->tokens)->toHaveCount(1);
    expect($user->tokens->first()->name)->toBe('Naif iPhone');
});

it('returns 422 when required registration fields are missing', function () {
    $response = $this->postJson('/api/v1/auth/register');

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password', 'device_name'])
        ->assertJsonPath('errors.email.0', 'The email field is required.');
});

it('returns 422 when the email is already registered', function () {
    User::factory()->create(['email' => 'naif@example.com']);

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Another User',
        'email' => 'NAIF@example.com',
        'password' => 'correct-horse-battery-staple',
        'password_confirmation' => 'correct-horse-battery-staple',
        'device_name' => 'Android phone',
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email')
        ->assertJsonPath('errors.email.0', 'The email has already been taken.');

    $this->assertDatabaseCount('users', 1);
});
