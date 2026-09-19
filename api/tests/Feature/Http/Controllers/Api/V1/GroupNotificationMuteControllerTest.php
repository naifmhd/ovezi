<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

it('lets an active member mute and unmute a group', function () {
    $user = User::factory()->create();
    $group = Group::factory()->for($user, 'creator')->create();
    $membership = GroupMember::factory()->owner()->for($group)->for($user)->create();
    $token = $user->createToken('Phone');

    $this->withToken($token->plainTextToken)
        ->putJson("/api/v1/groups/{$group->id}/notification-mute")
        ->assertOk()
        ->assertJsonPath('data.muted', true);
    expect($membership->fresh()->notifications_muted_at)->not->toBeNull();

    $this->withToken($token->plainTextToken)
        ->deleteJson("/api/v1/groups/{$group->id}/notification-mute")
        ->assertOk()
        ->assertJsonPath('data.muted', false);
    expect($membership->fresh()->notifications_muted_at)->toBeNull();
});

it('returns 404 when a non-member tries to mute a group', function () {
    $owner = User::factory()->create();
    $outsider = User::factory()->create();
    $group = Group::factory()->for($owner, 'creator')->create();
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    $token = $outsider->createToken('Phone');

    $this->withToken($token->plainTextToken)
        ->putJson("/api/v1/groups/{$group->id}/notification-mute")
        ->assertNotFound();
});
