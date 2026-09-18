<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

test('active group members can view while only owners can manage the group', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $group = Group::factory()->for($owner, 'creator')->create();
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    GroupMember::factory()->for($group)->for($member)->create();

    expect($owner->can('view', $group))->toBeTrue();
    expect($owner->can('update', $group))->toBeTrue();
    expect($member->can('view', $group))->toBeTrue();
    expect($member->can('update', $group))->toBeFalse();
});

test('inactive members and outsiders cannot view the group', function () {
    $owner = User::factory()->create();
    $inactiveMember = User::factory()->create();
    $outsider = User::factory()->create();
    $group = Group::factory()->for($owner, 'creator')->create();
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    GroupMember::factory()->for($group)->for($inactiveMember)->create([
        'left_at' => now(),
    ]);

    expect($inactiveMember->can('view', $group))->toBeFalse();
    expect($outsider->can('view', $group))->toBeFalse();
});
