<?php

use App\GroupMemberRole;
use App\Models\Currency;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

it('atomically transfers ownership to an active registered member', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $newOwner = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    $ownerMembership = GroupMember::factory()->owner()->for($group)->for($owner)->create();
    $newOwnerMembership = GroupMember::factory()->for($group)->for($newOwner)->create();
    $token = $owner->createToken('Owner phone');

    $this->withToken($token->plainTextToken)
        ->putJson("/api/v1/groups/{$group->id}/owner", ['user_id' => $newOwner->id])
        ->assertOk()
        ->assertJsonPath('data.id', $group->id);

    expect($ownerMembership->fresh()->role)->toBe(GroupMemberRole::Member)
        ->and($newOwnerMembership->fresh()->role)->toBe(GroupMemberRole::Owner)
        ->and($group->fresh()->created_by)->toBe($owner->id);
    $this->assertDatabaseHas('activity_logs', [
        'group_id' => $group->id,
        'actor_id' => $owner->id,
        'event' => 'group.ownership_transferred',
    ]);

    app('auth')->forgetGuards();
    $newOwnerToken = $newOwner->createToken('New owner phone');
    $this->withToken($newOwnerToken->plainTextToken)
        ->patchJson("/api/v1/groups/{$group->id}", ['name' => 'New owner group'])
        ->assertOk();
});

it('requires an active registered group member as the new owner', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
    $formerMember = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    GroupMember::factory()->for($group)->for($formerMember)->create(['left_at' => now()]);
    $token = $owner->createToken('Owner phone');

    foreach ([$outsider, $formerMember] as $target) {
        $this->withToken($token->plainTextToken)
            ->putJson("/api/v1/groups/{$group->id}/owner", ['user_id' => $target->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');
    }
});

it('blocks transfer by non-owners and while the group is archived', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $member = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    GroupMember::factory()->for($group)->for($member)->create();

    $memberToken = $member->createToken('Member phone');
    $this->withToken($memberToken->plainTextToken)
        ->putJson("/api/v1/groups/{$group->id}/owner", ['user_id' => $owner->id])
        ->assertForbidden();

    app('auth')->forgetGuards();
    $group->update(['archived_at' => now()]);
    $ownerToken = $owner->createToken('Owner phone');
    $this->withToken($ownerToken->plainTextToken)
        ->putJson("/api/v1/groups/{$group->id}/owner", ['user_id' => $member->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('group');
});
