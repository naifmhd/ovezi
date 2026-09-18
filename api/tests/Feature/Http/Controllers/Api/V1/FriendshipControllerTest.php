<?php

use App\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;

it('creates an exact-email friend request in canonical order', function () {
    $requester = User::factory()->create(['email' => 'requester@example.com']);
    $recipient = User::factory()->create(['email' => 'friend@example.com']);

    $this->withToken($requester->createToken('Requester phone')->plainTextToken)
        ->postJson('/api/v1/friends', ['email' => ' FRIEND@example.com '])
        ->assertCreated()
        ->assertJsonPath('data.friend.id', $recipient->id)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.direction', 'outgoing');

    $friendship = Friendship::query()->sole();
    expect($friendship->user_id)->toBe(min($requester->id, $recipient->id))
        ->and($friendship->friend_id)->toBe(max($requester->id, $recipient->id))
        ->and($friendship->requested_by)->toBe($requester->id);
});

it('lists incoming and outgoing friend requests from the current user perspective', function () {
    $user = User::factory()->create();
    $incomingUser = User::factory()->create();
    $outgoingUser = User::factory()->create();
    Friendship::query()->create([
        'user_id' => min($user->id, $incomingUser->id),
        'friend_id' => max($user->id, $incomingUser->id),
        'requested_by' => $incomingUser->id,
        'status' => FriendshipStatus::Pending,
    ]);
    Friendship::query()->create([
        'user_id' => min($user->id, $outgoingUser->id),
        'friend_id' => max($user->id, $outgoingUser->id),
        'requested_by' => $user->id,
        'status' => FriendshipStatus::Pending,
    ]);

    $response = $this->withToken($user->createToken('User phone')->plainTextToken)
        ->getJson('/api/v1/friends')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    $directions = collect($response->json('data'))
        ->keyBy('friend.id')
        ->map(fn (array $friendship): string => $friendship['direction']);
    expect($directions[$incomingUser->id])->toBe('incoming')
        ->and($directions[$outgoingUser->id])->toBe('outgoing');
});

it('does not let the requester accept their own pending friend request', function () {
    $requester = User::factory()->create();
    $recipient = User::factory()->create();
    $friendship = Friendship::query()->create([
        'user_id' => min($requester->id, $recipient->id),
        'friend_id' => max($requester->id, $recipient->id),
        'requested_by' => $requester->id,
        'status' => FriendshipStatus::Pending,
    ]);

    $this->withToken($requester->createToken('Requester phone')->plainTextToken)
        ->postJson("/api/v1/friends/{$friendship->id}/accept")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('friendship');
});

it('lets the recipient accept a pending friend request', function () {
    $requester = User::factory()->create();
    $recipient = User::factory()->create();
    $friendship = Friendship::query()->create([
        'user_id' => min($requester->id, $recipient->id),
        'friend_id' => max($requester->id, $recipient->id),
        'requested_by' => $requester->id,
        'status' => FriendshipStatus::Pending,
    ]);

    $this->withToken($recipient->createToken('Recipient phone')->plainTextToken)
        ->postJson("/api/v1/friends/{$friendship->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'accepted')
        ->assertJsonPath('data.direction', 'incoming');

    expect($friendship->fresh()->status)->toBe(FriendshipStatus::Accepted)
        ->and($friendship->fresh()->accepted_at)->not->toBeNull();
});

it('allows either participant to cancel, decline, or remove a friendship', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $friendship = Friendship::query()->create([
        'user_id' => min($first->id, $second->id),
        'friend_id' => max($first->id, $second->id),
        'requested_by' => $first->id,
        'status' => FriendshipStatus::Accepted,
        'accepted_at' => now(),
    ]);

    $this->withToken($second->createToken('Second phone')->plainTextToken)
        ->deleteJson("/api/v1/friends/{$friendship->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('friendships', ['id' => $friendship->id]);
});

it('does not reveal accounts through partial or unknown email requests', function () {
    $requester = User::factory()->create();
    User::factory()->create(['email' => 'friend@example.com']);
    $token = $requester->createToken('Requester phone')->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/friends', ['email' => 'friend'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
    $this->withToken($token)
        ->postJson('/api/v1/friends', ['email' => 'unknown@example.com'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('email');
});
