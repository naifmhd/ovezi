<?php

use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

it('returns personal and active-group activity without leaking other groups', function () {
    $currency = Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => $currency->code]);
    $otherUser = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($user, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    $otherGroup = Group::factory()->for($otherUser, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($user)->create();
    GroupMember::factory()->owner()->for($otherGroup)->for($otherUser)->create();
    ActivityLog::factory()->create([
        'group_id' => $group->id,
        'actor_id' => $otherUser->id,
        'subject_type' => Group::class,
        'subject_id' => $group->id,
        'event' => 'group.updated',
        'metadata' => ['after' => ['name' => 'Trip']],
        'created_at' => now()->subMinute(),
    ]);
    ActivityLog::factory()->create([
        'group_id' => null,
        'actor_id' => $user->id,
        'subject_type' => Expense::class,
        'subject_id' => 99,
        'event' => 'expense.created',
        'created_at' => now(),
    ]);
    ActivityLog::factory()->create([
        'group_id' => $otherGroup->id,
        'actor_id' => $otherUser->id,
        'subject_type' => Group::class,
        'subject_id' => $otherGroup->id,
        'event' => 'group.archived',
    ]);
    $token = $user->createToken('User phone');

    $response = $this->withToken($token->plainTextToken)->getJson('/api/v1/activity?per_page=10');

    $response
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.event', 'expense.created')
        ->assertJsonPath('data.0.subject.type', 'expense')
        ->assertJsonPath('data.0.actor.id', $user->id)
        ->assertJsonPath('data.1.event', 'group.updated')
        ->assertJsonPath('data.1.metadata.after.name', 'Trip')
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonMissing(['event' => 'group.archived']);
});

it('does not include group activity after the user leaves', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $formerMember = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    GroupMember::factory()->for($group)->for($formerMember)->create(['left_at' => now()]);
    ActivityLog::factory()->create([
        'group_id' => $group->id,
        'actor_id' => $owner->id,
        'subject_type' => Group::class,
        'subject_id' => $group->id,
        'event' => 'group.updated',
    ]);
    $token = $formerMember->createToken('Former member phone');

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/activity')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('includes direct expense activity for a registered split participant', function () {
    $currency = Currency::factory()->mvr()->create();
    $creator = User::factory()->create(['default_currency_code' => $currency->code]);
    $participant = User::factory()->create(['default_currency_code' => $currency->code]);
    $expense = Expense::factory()->create([
        'payer_user_id' => $creator->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
        'created_by' => $creator->id,
    ]);
    ExpenseSplit::factory()->for($expense)->for($creator)->create();
    ExpenseSplit::factory()->for($expense)->for($participant)->create();
    ActivityLog::factory()->create([
        'group_id' => null,
        'actor_id' => $creator->id,
        'subject_type' => $expense->getMorphClass(),
        'subject_id' => $expense->id,
        'event' => 'expense.created',
    ]);
    $token = $participant->createToken('Participant phone');

    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/activity')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.subject.id', $expense->id)
        ->assertJsonPath('data.0.actor.id', $creator->id);
});

it('returns a group-specific feed to members and hides it from outsiders', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $member = User::factory()->create(['default_currency_code' => $currency->code]);
    $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    GroupMember::factory()->for($group)->for($member)->create();
    ActivityLog::factory()->create([
        'group_id' => $group->id,
        'actor_id' => $owner->id,
        'subject_type' => Group::class,
        'subject_id' => $group->id,
        'event' => 'group.created',
    ]);

    $memberToken = $member->createToken('Member phone');
    $this->withToken($memberToken->plainTextToken)
        ->getJson("/api/v1/groups/{$group->id}/activity")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event', 'group.created');

    app('auth')->forgetGuards();
    $outsiderToken = $outsider->createToken('Outsider phone');
    $this->withToken($outsiderToken->plainTextToken)
        ->getJson("/api/v1/groups/{$group->id}/activity")
        ->assertNotFound();
});

it('requires authentication and validates pagination', function () {
    $this->getJson('/api/v1/activity')->assertUnauthorized();

    $user = User::factory()->create();
    $token = $user->createToken('User phone');
    $this->withToken($token->plainTextToken)
        ->getJson('/api/v1/activity?per_page=101')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');
});
