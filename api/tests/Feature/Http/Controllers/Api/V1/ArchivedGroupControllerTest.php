<?php

use App\Models\Currency;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

it('returns 403 when a member archives the group', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $member = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    GroupMember::factory()->for($group)->for($member)->create();
    $token = $member->createToken('Member phone');

    $response = $this
        ->withToken($token->plainTextToken)
        ->putJson("/api/v1/groups/{$group->id}/archive");

    $response->assertForbidden();
    expect($group->fresh()->archived_at)->toBeNull();
});

it('lets an owner archive a group once', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    $token = $owner->createToken('Owner phone');

    $firstResponse = $this
        ->withToken($token->plainTextToken)
        ->putJson("/api/v1/groups/{$group->id}/archive");
    $secondResponse = $this
        ->withToken($token->plainTextToken)
        ->putJson("/api/v1/groups/{$group->id}/archive");

    $firstResponse
        ->assertOk()
        ->assertJsonPath('data.is_archived', true);
    $secondResponse
        ->assertOk()
        ->assertJsonPath('data.is_archived', true);
    expect($group->fresh()->archived_at)->not->toBeNull();
    $this->assertDatabaseCount('activity_logs', 1);
    $this->assertDatabaseHas('activity_logs', [
        'group_id' => $group->id,
        'actor_id' => $owner->id,
        'event' => 'group.archived',
    ]);
});

it('lets an owner reopen an archived group', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
        'archived_at' => now(),
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    $token = $owner->createToken('Owner phone');

    $response = $this
        ->withToken($token->plainTextToken)
        ->deleteJson("/api/v1/groups/{$group->id}/archive");

    $response
        ->assertOk()
        ->assertJsonPath('data.is_archived', false)
        ->assertJsonPath('data.archived_at', null);
    expect($group->fresh()->archived_at)->toBeNull();
    $this->assertDatabaseHas('activity_logs', [
        'group_id' => $group->id,
        'actor_id' => $owner->id,
        'event' => 'group.reopened',
    ]);
});
