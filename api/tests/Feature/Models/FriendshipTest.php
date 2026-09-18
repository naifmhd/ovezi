<?php

use App\FriendshipStatus;
use App\Models\Friendship;
use App\Models\User;

it('stores a friendship in canonical user order', function () {
    $friendship = Friendship::factory()->create();

    expect($friendship->user_id)->toBeLessThan($friendship->friend_id);
    expect($friendship->status)->toBe(FriendshipStatus::Pending);
    expect($friendship->requested_by)->toBe($friendship->user_id);
});

it('rejects friendships that are not in canonical order', function () {
    $firstUser = User::factory()->create();
    $secondUser = User::factory()->create();

    expect(fn () => Friendship::create([
        'user_id' => $secondUser->id,
        'friend_id' => $firstUser->id,
        'requested_by' => $firstUser->id,
        'status' => FriendshipStatus::Pending,
    ]))->toThrow(InvalidArgumentException::class, 'canonical ascending order');
});
