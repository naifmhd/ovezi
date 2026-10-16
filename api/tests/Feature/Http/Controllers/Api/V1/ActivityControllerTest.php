<?php

use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\LendingContact;
use App\Models\LendingLoan;
use App\Models\Settlement;
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
        ->assertJsonPath('data.0.group', null)
        ->assertJsonPath('data.1.event', 'group.updated')
        ->assertJsonPath('data.1.group', ['id' => $group->id, 'name' => $group->name])
        ->assertJsonPath('data.1.metadata.after.name', 'Trip')
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonMissing(['event' => 'group.archived']);
    $this->getJson('/api/v1/activity?source=groups')->assertJsonCount(1, 'data')->assertJsonPath('data.0.group_id', $group->id);
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

it('includes direct settlement activity for the other participant', function () {
    $currency = Currency::factory()->mvr()->create();
    $sender = User::factory()->create(['default_currency_code' => $currency->code]);
    $recipient = User::factory()->create(['default_currency_code' => $currency->code]);
    $settlement = Settlement::factory()->create([
        'group_id' => null,
        'from_user_id' => $sender->id,
        'to_user_id' => $recipient->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
        'created_by' => $sender->id,
    ]);
    ActivityLog::factory()->create([
        'group_id' => null,
        'actor_id' => $sender->id,
        'subject_type' => $settlement->getMorphClass(),
        'subject_id' => $settlement->id,
        'event' => 'settlement.created',
    ]);

    $this->actingAs($recipient)
        ->getJson('/api/v1/activity')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.subject.type', 'settlement')
        ->assertJsonPath('data.0.subject.id', $settlement->id);
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

it('merges lending and expense history in stable paginated order and filters at the database', function () {
    $this->freezeTime();
    Currency::factory()->mvr()->create();
    $user = User::factory()->create();
    $person = LendingContact::factory()->create(['owner_id' => $user->id, 'name' => 'Aishath']);
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'private_note' => 'Secret note']);
    $created = $loan->events()->create(['actor_id' => $user->id, 'event' => 'loan.created', 'details' => ['private_note' => 'Secret note']]);
    $repaid = $loan->events()->create(['actor_id' => $user->id, 'event' => 'repayment.confirmed', 'details' => ['amount_minor' => 300]]);
    $expense = ActivityLog::factory()->create(['actor_id' => $user->id, 'group_id' => null, 'subject_type' => Expense::class, 'subject_id' => 99, 'event' => 'expense.created', 'created_at' => now()->subMinute()]);

    $this->actingAs($user)->getJson('/api/v1/activity?per_page=1')->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.timeline_id', 'lending:'.$repaid->id)->assertJsonPath('data.0.metadata.amount_minor', 300)->assertJsonPath('meta.total', 3);
    $this->getJson('/api/v1/activity?per_page=1&page=2')->assertJsonPath('data.0.timeline_id', 'lending:'.$created->id)
        ->assertJsonPath('data.0.person.name', 'Aishath')->assertJsonMissingPath('data.0.metadata.private_note')->assertJsonMissing(['private_note' => 'Secret note']);
    $this->getJson('/api/v1/activity?per_page=1&page=3')->assertJsonPath('data.0.timeline_id', 'activity:'.$expense->id);
    $this->getJson('/api/v1/activity?source=lending')->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
    $this->getJson('/api/v1/activity?source=personal')->assertJsonCount(1, 'data')->assertJsonPath('data.0.event', 'expense.created');
    $this->getJson('/api/v1/activity?source=groups')->assertJsonCount(0, 'data');
});

it('only exposes explicitly shared loan history with the counterpart name and direction', function () {
    Currency::factory()->mvr()->create();
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $stranger = User::factory()->create();
    $person = LendingContact::factory()->create(['owner_id' => $owner->id, 'name' => 'Owner private contact label', 'connected_user_id' => $recipient->id, 'email_hash' => hash('sha256', $stranger->email)]);
    $shared = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'shared_with' => $recipient->id, 'shared_at' => now(), 'private_note' => 'Keep private', 'voided_at' => now()]);
    $shared->events()->create(['actor_id' => $owner->id, 'event' => 'loan.void']);
    $private = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR']);
    $private->events()->create(['actor_id' => $owner->id, 'event' => 'loan.created']);

    $this->actingAs($owner)->getJson('/api/v1/activity?source=lending')->assertJsonCount(2, 'data');
    $this->actingAs($recipient)->getJson('/api/v1/activity')->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.subject.id', $shared->id)->assertJsonPath('data.0.person.name', $owner->name)
        ->assertJsonPath('data.0.metadata.direction', 'borrowed')->assertJsonMissingPath('data.0.private_note')
        ->assertJsonMissing(['name' => 'Owner private contact label']);
    $this->actingAs($stranger)->getJson('/api/v1/activity')->assertJsonCount(0, 'data');
});

it('validates activity filters and pagination', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/v1/activity?source=unknown&page=0')
        ->assertUnprocessable()->assertJsonValidationErrors(['source', 'page']);
});

it('searches visible descriptions and names before pagination without matching private contact labels or notes', function () {
    Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['name' => 'Ibrahim']);
    $viewer = User::factory()->create();
    $contact = LendingContact::factory()->create(['owner_id' => $owner->id, 'connected_user_id' => $viewer->id, 'name' => 'Secret nickname']);
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $contact->id, 'currency_code' => 'MVR', 'description' => 'Island deposit', 'private_note' => 'Secret note', 'shared_with' => $viewer->id, 'shared_at' => now()]);
    $loan->events()->create(['actor_id' => $owner->id, 'event' => 'loan.created']);
    $private = LendingLoan::factory()->create(['currency_code' => 'MVR', 'description' => 'Island deposit']);
    $private->events()->create(['actor_id' => $owner->id, 'event' => 'loan.created']);
    ActivityLog::factory()->create(['actor_id' => $viewer->id, 'group_id' => null, 'subject_type' => Expense::class, 'subject_id' => 999, 'metadata' => ['description' => 'Island meal']]);

    $this->actingAs($viewer)->getJson('/api/v1/activity?q=Island&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2);
    $this->getJson('/api/v1/activity?q=Island&source=lending')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.subject.id', $loan->id);
    $this->getJson('/api/v1/activity?q=Ibrahim')->assertJsonPath('meta.total', 1);
    $this->getJson('/api/v1/activity?q=Secret')->assertJsonCount(0, 'data');
    $this->actingAs($owner)->getJson('/api/v1/activity?q=nickname')->assertJsonPath('data.0.subject.id', $loan->id);
});

it('filters both timeline sources by inclusive start and exclusive end instants', function () {
    Currency::factory()->mvr()->create();
    $user = User::factory()->create();
    $contact = LendingContact::factory()->create(['owner_id' => $user->id]);
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $contact->id, 'currency_code' => 'MVR']);
    foreach (['2026-10-14 18:59:59', '2026-10-14 19:00:00', '2026-10-15 18:59:59', '2026-10-15 19:00:00'] as $instant) {
        $loan->events()->create(['actor_id' => $user->id, 'event' => 'loan.created'])->forceFill(['created_at' => $instant])->save();
        ActivityLog::factory()->create(['actor_id' => $user->id, 'group_id' => null, 'subject_type' => Expense::class, 'subject_id' => 99, 'created_at' => $instant]);
    }
    $this->actingAs($user)->getJson('/api/v1/activity?after=2026-10-14T19:00:00.000Z&before=2026-10-15T19:00:00.000Z')->assertOk()->assertJsonCount(4, 'data');
    $this->getJson('/api/v1/activity?before=2026-10-14T19:00:00.000Z')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/activity?after=2026-10-15T19:00:00.000Z&before=2026-10-14T19:00:00.000Z')->assertUnprocessable()->assertJsonValidationErrors('before');
    $this->getJson('/api/v1/activity?after=not-a-date&q='.str_repeat('x', 121))->assertUnprocessable()->assertJsonValidationErrors(['after', 'q']);
});
