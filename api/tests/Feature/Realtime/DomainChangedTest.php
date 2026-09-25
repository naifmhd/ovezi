<?php

use App\Events\DomainChanged;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\RealtimeAudience;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Event;

it('dispatches a realtime change when an observed domain model changes', function () {
    $currency = Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($user, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);

    Event::fake([DomainChanged::class]);

    $expense = Expense::factory()->for($group)->create([
        'expense_type' => 'group',
        'payer_user_id' => $user->id,
        'created_by' => $user->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);

    Event::assertDispatched(
        DomainChanged::class,
        fn (DomainChanged $event): bool => $event->resource === 'expense'
            && $event->action === 'created'
            && $event->resourceId === $expense->id
            && $event->groupId === $group->id,
    );
});

it('broadcasts an expense change only to affected users', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $member = User::factory()->create(['default_currency_code' => $currency->code]);
    $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    GroupMember::factory()->for($group)->for($member)->create();
    $expense = Expense::factory()->for($group)->create([
        'expense_type' => 'group',
        'payer_user_id' => $owner->id,
        'created_by' => $owner->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);
    ExpenseSplit::factory()->for($expense)->for($owner)->create();

    $audience = app(RealtimeAudience::class)->for('expense', $expense->id);
    $event = new DomainChanged('expense', 'created', $expense->id, $group->id);
    $channelNames = collect($event->broadcastOn())->pluck('name')->all();

    expect($audience)
        ->toContain($owner->id, $member->id)
        ->not->toContain($outsider->id)
        ->and($channelNames)
        ->toContain("private-users.{$owner->id}", "private-users.{$member->id}")
        ->not->toContain("private-users.{$outsider->id}")
        ->and($event->broadcastAs())->toBe('domain.changed')
        ->and($event->broadcastQueue())->toBe('broadcasts')
        ->and($event->broadcastWith())->toMatchArray([
            'version' => 1,
            'resource' => 'expense',
            'action' => 'created',
            'resource_id' => $expense->id,
            'group_id' => $group->id,
        ])
        ->and($event->broadcastWith()['event_id'])->toBeString()->not->toBeEmpty()
        ->and($event->broadcastWith()['occurred_at'])->toBeString()->not->toBeEmpty();
});

it('captures the audience before a hard-deleted resource disappears', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    $userId = min($first->id, $second->id);
    $friendId = max($first->id, $second->id);
    $friendship = Friendship::factory()->create([
        'user_id' => $userId,
        'friend_id' => $friendId,
        'requested_by' => $userId,
    ]);

    Event::fake([DomainChanged::class]);

    $friendship->delete();

    Event::assertDispatched(
        DomainChanged::class,
        fn (DomainChanged $event): bool => $event->resource === 'friendship'
            && $event->action === 'deleted'
            && $event->resourceId === $friendship->id
            && $event->audience === [$userId, $friendId],
    );
});

it('authorizes only the matching users private channel', function () {
    config()->set('broadcasting.default', 'reverb');
    config()->set('broadcasting.connections.reverb', [
        'driver' => 'reverb',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'app_id' => 'test-app',
        'options' => [
            'host' => 'localhost',
            'port' => 443,
            'scheme' => 'https',
            'useTLS' => true,
        ],
        'client_options' => [],
    ]);
    Broadcast::forgetDrivers();
    Broadcast::channel(
        'users.{userId}',
        fn (User $authenticatedUser, string $userId): bool => $authenticatedUser->id === (int) $userId,
    );
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $token = $user->createToken('User phone')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => "private-users.{$user->id}",
    ])->assertOk()->assertJsonStructure(['auth']);

    $this->withToken($token)->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => "private-users.{$otherUser->id}",
    ])->assertForbidden();
});

it('requires a sanctum token for private channel authorization', function () {
    $this->postJson('/api/v1/broadcasting/auth', [
        'socket_id' => '123.456',
        'channel_name' => 'private-users.1',
    ])->assertUnauthorized();
});
