<?php

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Placeholder;
use App\Models\User;

it('accepts a registered user as the only participant reference', function () {
    $member = GroupMember::factory()->create();

    $this->assertModelExists($member);
    expect($member->user_id)->not->toBeNull();
    expect($member->placeholder_id)->toBeNull();
});

it('rejects multiple participant references', function () {
    $group = Group::factory()->create();
    $user = User::factory()->create();
    $placeholder = Placeholder::factory()->create();

    expect(fn () => GroupMember::factory()->create([
        'group_id' => $group->id,
        'user_id' => $user->id,
        'placeholder_id' => $placeholder->id,
    ]))->toThrow(InvalidArgumentException::class, 'exactly one user or placeholder');
});
