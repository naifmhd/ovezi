<?php

use App\GroupMemberRole;
use App\Models\Currency;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Placeholder;
use App\Models\User;

describe('index', function () {
    it('returns 401 when no access token is provided', function () {
        $this->getJson('/api/v1/groups')->assertUnauthorized();
    });

    it('lists only active groups where the user is an active member', function () {
        $currency = Currency::factory()->mvr()->create();
        $user = User::factory()->create(['default_currency_code' => $currency->code]);
        $otherUser = User::factory()->create(['default_currency_code' => $currency->code]);
        $activeGroup = Group::factory()->for($user, 'creator')->create([
            'name' => 'Active trip',
            'reporting_currency_code' => $currency->code,
        ]);
        $archivedGroup = Group::factory()->for($user, 'creator')->create([
            'name' => 'Archived trip',
            'reporting_currency_code' => $currency->code,
            'archived_at' => now(),
        ]);
        $leftGroup = Group::factory()->for($otherUser, 'creator')->create([
            'name' => 'Former trip',
            'reporting_currency_code' => $currency->code,
        ]);
        $otherGroup = Group::factory()->for($otherUser, 'creator')->create([
            'name' => 'Other trip',
            'reporting_currency_code' => $currency->code,
        ]);
        GroupMember::factory()->owner()->for($activeGroup)->for($user)->create();
        GroupMember::factory()->owner()->for($archivedGroup)->for($user)->create();
        GroupMember::factory()->for($leftGroup)->for($user)->create(['left_at' => now()]);
        GroupMember::factory()->owner()->for($otherGroup)->for($otherUser)->create();
        $token = $user->createToken('User phone');

        $response = $this->withToken($token->plainTextToken)->getJson('/api/v1/groups');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $activeGroup->id)
            ->assertJsonPath('data.0.active_member_count', 1)
            ->assertJsonPath('meta.per_page', 20);
    });

    it('can list archived groups explicitly', function () {
        $currency = Currency::factory()->mvr()->create();
        $user = User::factory()->create(['default_currency_code' => $currency->code]);
        $activeGroup = Group::factory()->for($user, 'creator')->create([
            'reporting_currency_code' => $currency->code,
        ]);
        $archivedGroup = Group::factory()->for($user, 'creator')->create([
            'reporting_currency_code' => $currency->code,
            'archived_at' => now(),
        ]);
        GroupMember::factory()->owner()->for($activeGroup)->for($user)->create();
        GroupMember::factory()->owner()->for($archivedGroup)->for($user)->create();
        $token = $user->createToken('User phone');

        $response = $this
            ->withToken($token->plainTextToken)
            ->getJson('/api/v1/groups?status=archived');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $archivedGroup->id)
            ->assertJsonPath('data.0.is_archived', true);
    });
});

describe('store', function () {
    it('returns 422 with clear messages for invalid group data', function () {
        $user = User::factory()->create();
        $token = $user->createToken('User phone');

        $response = $this
            ->withToken($token->plainTextToken)
            ->postJson('/api/v1/groups');

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'reporting_currency_code'])
            ->assertJsonPath('errors.name.0', 'The name field is required.');
        $this->assertDatabaseCount('groups', 0);
        $this->assertDatabaseCount('group_members', 0);
    });

    it('creates a group with its creator as owner', function () {
        $currency = Currency::factory()->mvr()->create();
        $creator = User::factory()->create(['default_currency_code' => $currency->code]);
        $otherUser = User::factory()->create(['default_currency_code' => $currency->code]);
        $token = $creator->createToken('Creator phone');

        $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/groups', [
            'name' => ' Maldives Trip ',
            'reporting_currency_code' => 'mvr',
            'created_by' => $otherUser->id,
            'archived_at' => now()->toISOString(),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Maldives Trip')
            ->assertJsonPath('data.reporting_currency_code', 'MVR')
            ->assertJsonPath('data.created_by', $creator->id)
            ->assertJsonPath('data.active_member_count', 1)
            ->assertJsonPath('data.is_archived', false);
        $group = Group::query()->sole();
        $this->assertDatabaseHas('group_members', [
            'group_id' => $group->id,
            'user_id' => $creator->id,
            'role' => GroupMemberRole::Owner->value,
            'left_at' => null,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'group_id' => $group->id,
            'actor_id' => $creator->id,
            'event' => 'group.created',
        ]);
    });
});

describe('show', function () {
    it('returns active users and placeholders without private placeholder contact data', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $member = User::factory()->create(['default_currency_code' => $currency->code]);
        $placeholder = Placeholder::factory()->for($owner, 'creator')->create(['name' => 'Guest']);
        $group = Group::factory()->for($owner, 'creator')->create([
            'reporting_currency_code' => $currency->code,
        ]);
        GroupMember::factory()->owner()->for($group)->for($owner)->create();
        GroupMember::factory()->for($group)->for($member)->create();
        GroupMember::factory()->for($group)->create([
            'user_id' => null,
            'placeholder_id' => $placeholder->id,
        ]);
        $token = $member->createToken('Member phone');

        $response = $this->withToken($token->plainTextToken)->getJson("/api/v1/groups/{$group->id}");

        $response
            ->assertOk()
            ->assertJsonCount(3, 'data.members')
            ->assertJsonFragment(['name' => 'Guest'])
            ->assertJsonMissingPath('data.members.2.placeholder.contact_value')
            ->assertJsonMissingPath('data.members.2.placeholder.contact_hash');
    });

    it('returns 404 when the user is not a group member', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = Group::factory()->for($owner, 'creator')->create([
            'reporting_currency_code' => $currency->code,
        ]);
        GroupMember::factory()->owner()->for($group)->for($owner)->create();
        $token = $outsider->createToken('Outsider phone');

        $this->withToken($token->plainTextToken)
            ->getJson("/api/v1/groups/{$group->id}")
            ->assertNotFound();
    });
});

describe('update', function () {
    it('returns 403 when a non-owner member changes group settings', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $member = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = Group::factory()->for($owner, 'creator')->create([
            'name' => 'Original name',
            'reporting_currency_code' => $currency->code,
        ]);
        GroupMember::factory()->owner()->for($group)->for($owner)->create();
        GroupMember::factory()->for($group)->for($member)->create();
        $token = $member->createToken('Member phone');

        $response = $this->withToken($token->plainTextToken)->patchJson("/api/v1/groups/{$group->id}", [
            'name' => 'Changed name',
        ]);

        $response->assertForbidden();
        expect($group->fresh()->name)->toBe('Original name');
    });

    it('lets an owner change group settings and records the change', function () {
        $mvr = Currency::factory()->mvr()->create();
        $usd = Currency::factory()->usd()->create();
        $owner = User::factory()->create(['default_currency_code' => $mvr->code]);
        $group = Group::factory()->for($owner, 'creator')->create([
            'name' => 'Original name',
            'reporting_currency_code' => $mvr->code,
        ]);
        GroupMember::factory()->owner()->for($group)->for($owner)->create();
        $token = $owner->createToken('Owner phone');

        $response = $this->withToken($token->plainTextToken)->patchJson("/api/v1/groups/{$group->id}", [
            'name' => ' Updated name ',
            'reporting_currency_code' => 'usd',
            'created_by' => User::factory()->create()->id,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.name', 'Updated name')
            ->assertJsonPath('data.reporting_currency_code', $usd->code)
            ->assertJsonPath('data.created_by', $owner->id);
        $this->assertDatabaseHas('activity_logs', [
            'group_id' => $group->id,
            'actor_id' => $owner->id,
            'event' => 'group.updated',
        ]);
    });

    it('returns 422 when changing settings for an archived group', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = Group::factory()->for($owner, 'creator')->create([
            'name' => 'Archived group',
            'reporting_currency_code' => $currency->code,
            'archived_at' => now(),
        ]);
        GroupMember::factory()->owner()->for($group)->for($owner)->create();
        $token = $owner->createToken('Owner phone');

        $response = $this->withToken($token->plainTextToken)->patchJson("/api/v1/groups/{$group->id}", [
            'name' => 'Changed name',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('group')
            ->assertJsonPath('errors.group.0', 'Reopen the group before changing its settings.');
        expect($group->fresh()->name)->toBe('Archived group');
    });
});
