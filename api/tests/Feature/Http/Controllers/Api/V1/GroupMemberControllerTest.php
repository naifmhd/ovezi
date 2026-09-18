<?php

use App\GroupMemberRole;
use App\Models\Currency;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Placeholder;
use App\Models\User;

function ownedGroupFor(User $owner, Currency $currency, array $attributes = []): Group
{
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
        ...$attributes,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();

    return $group;
}

describe('store', function () {
    it('requires authentication', function () {
        $this->postJson('/api/v1/groups/1/members')->assertUnauthorized();
    });

    it('lets an owner add a registered user and records activity', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $member = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = ownedGroupFor($owner, $currency);
        $token = $owner->createToken('Owner phone');

        $response = $this->withToken($token->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/members", [
                'user_id' => $member->id,
                'role' => GroupMemberRole::Owner->value,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.role', GroupMemberRole::Member->value)
            ->assertJsonPath('data.user.id', $member->id)
            ->assertJsonMissingPath('data.placeholder');
        $membership = GroupMember::query()
            ->where('group_id', $group->id)
            ->where('user_id', $member->id)
            ->sole();
        $this->assertDatabaseHas('activity_logs', [
            'group_id' => $group->id,
            'actor_id' => $owner->id,
            'subject_id' => $membership->id,
            'event' => 'member.joined',
        ]);
    });

    it('lets an owner add their unclaimed placeholder without exposing contact data', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = ownedGroupFor($owner, $currency);
        $placeholder = Placeholder::factory()->for($owner, 'creator')->create(['name' => 'Guest']);
        $token = $owner->createToken('Owner phone');

        $response = $this->withToken($token->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/members", [
                'placeholder_id' => $placeholder->id,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.placeholder.id', $placeholder->id)
            ->assertJsonPath('data.placeholder.name', 'Guest')
            ->assertJsonMissingPath('data.placeholder.contact_value')
            ->assertJsonMissingPath('data.placeholder.contact_hash');
    });

    it('requires exactly one user or placeholder', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $user = User::factory()->create(['default_currency_code' => $currency->code]);
        $placeholder = Placeholder::factory()->for($owner, 'creator')->create();
        $group = ownedGroupFor($owner, $currency);
        $token = $owner->createToken('Owner phone');

        $this->withToken($token->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/members")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member');

        $this->withToken($token->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/members", [
                'user_id' => $user->id,
                'placeholder_id' => $placeholder->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member');
    });

    it('rejects duplicate active memberships', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $member = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = ownedGroupFor($owner, $currency);
        GroupMember::factory()->for($group)->for($member)->create();
        $token = $owner->createToken('Owner phone');

        $this->withToken($token->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/members", ['user_id' => $member->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('member');
    });

    it('reactivates a historical membership without creating a duplicate', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $member = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = ownedGroupFor($owner, $currency);
        $joinedAt = now()->subMonth();
        $membership = GroupMember::factory()->for($group)->for($member)->create([
            'joined_at' => $joinedAt,
            'left_at' => now()->subDay(),
        ]);
        $originalJoinedAt = $membership->joined_at->toDateTimeString();
        $token = $owner->createToken('Owner phone');

        $this->withToken($token->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/members", ['user_id' => $member->id])
            ->assertCreated()
            ->assertJsonPath('data.id', $membership->id);

        $membership->refresh();
        expect($membership->left_at)->toBeNull()
            ->and($membership->joined_at->toDateTimeString())->toBe($originalJoinedAt);
        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $membership->id,
            'event' => 'member.rejoined',
        ]);
    });

    it('rejects another owners placeholder and a claimed placeholder', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $otherOwner = User::factory()->create(['default_currency_code' => $currency->code]);
        $claimedBy = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = ownedGroupFor($owner, $currency);
        $otherPlaceholder = Placeholder::factory()->for($otherOwner, 'creator')->create();
        $claimedPlaceholder = Placeholder::factory()->for($owner, 'creator')->create([
            'claimed_by' => $claimedBy->id,
            'claimed_at' => now(),
        ]);
        $token = $owner->createToken('Owner phone');

        $this->withToken($token->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/members", ['placeholder_id' => $otherPlaceholder->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('placeholder_id');

        $this->withToken($token->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/members", ['placeholder_id' => $claimedPlaceholder->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('placeholder_id');
    });

    it('returns 403 for a non-owner member and 404 for an outsider', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $member = User::factory()->create(['default_currency_code' => $currency->code]);
        $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
        $newMember = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = ownedGroupFor($owner, $currency);
        GroupMember::factory()->for($group)->for($member)->create();

        $memberToken = $member->createToken('Member phone');
        $this->withToken($memberToken->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/members", ['user_id' => $newMember->id])
            ->assertForbidden();

        app('auth')->forgetGuards();
        $outsiderToken = $outsider->createToken('Outsider phone');
        $this->withToken($outsiderToken->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/members", ['user_id' => $newMember->id])
            ->assertNotFound();
    });

    it('does not add members to an archived group', function () {
        $currency = Currency::factory()->mvr()->create();
        $owner = User::factory()->create(['default_currency_code' => $currency->code]);
        $member = User::factory()->create(['default_currency_code' => $currency->code]);
        $group = ownedGroupFor($owner, $currency, ['archived_at' => now()]);
        $token = $owner->createToken('Owner phone');

        $this->withToken($token->plainTextToken)
            ->postJson("/api/v1/groups/{$group->id}/members", ['user_id' => $member->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('group');
    });
});
