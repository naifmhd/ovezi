<?php

use App\Models\Currency;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

it('returns default preferences and active group mute states', function () {
    $currency = Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => $currency->code]);
    $activeGroup = Group::factory()->for($user, 'creator')->create([
        'name' => 'Malé Weekend',
        'reporting_currency_code' => $currency->code,
    ]);
    $leftGroup = Group::factory()->for($user, 'creator')->create([
        'name' => 'Old trip',
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($activeGroup)->for($user)->create([
        'notifications_muted_at' => now(),
    ]);
    GroupMember::factory()->owner()->for($leftGroup)->for($user)->create(['left_at' => now()]);
    $token = $user->createToken('Phone');

    $this->withToken($token->plainTextToken)->getJson('/api/v1/notification-preferences')
        ->assertOk()
        ->assertJsonPath('data.expense_created', true)
        ->assertJsonPath('data.payment_received', true)
        ->assertJsonPath('data.settle_up_reminders', true)
        ->assertJsonCount(1, 'data.groups')
        ->assertJsonPath('data.groups.0.name', 'Malé Weekend')
        ->assertJsonPath('data.groups.0.muted', true);

    $this->assertDatabaseCount('notification_preferences', 0);
});

it('updates only supported notification preferences', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = $user->createToken('Phone');

    $this->withToken($token->plainTextToken)->patchJson('/api/v1/notification-preferences', [
        'expense_created' => false,
        'user_id' => $otherUser->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.expense_created', false)
        ->assertJsonPath('data.payment_received', true);

    $this->assertDatabaseHas('notification_preferences', [
        'user_id' => $user->id,
        'expense_created' => false,
        'payment_received' => true,
    ]);
    $this->assertDatabaseMissing('notification_preferences', ['user_id' => $otherUser->id]);
});

it('returns 422 when no preference is provided', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Phone');

    $this->withToken($token->plainTextToken)
        ->patchJson('/api/v1/notification-preferences', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('preferences')
        ->assertJsonPath('errors.preferences.0', 'Provide at least one notification preference to update.');

    $this->assertDatabaseCount('notification_preferences', 0);
});
