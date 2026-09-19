<?php

use App\Jobs\CheckExpoPushReceipts;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\NotificationPreference;
use App\Models\PushToken;
use App\Models\User;
use App\NotificationType;
use App\Services\ExpoPushService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('sends an allowed notification and queues its receipt check', function () {
    $user = User::factory()->create();
    $group = Group::factory()->for($user, 'creator')->create();
    GroupMember::factory()->owner()->for($group)->for($user)->create();
    $pushToken = PushToken::factory()->for($user)->create([
        'expo_push_token' => 'ExponentPushToken[allowed_device_123456]',
    ]);
    Queue::fake([CheckExpoPushReceipts::class]);
    Http::preventStrayRequests();
    Http::fake([
        'https://exp.host/--/api/v2/push/send' => Http::response([
            'data' => [['status' => 'ok', 'id' => 'receipt-123']],
        ]),
    ]);

    app(ExpoPushService::class)->sendToUsers(
        [$user->id],
        NotificationType::ExpenseCreated,
        $group->id,
        'New expense',
        'Dinner in Malé Weekend',
        ['expense_id' => 42],
    );

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://exp.host/--/api/v2/push/send'
        && $request->data()[0]['to'] === $pushToken->expo_push_token
        && $request->data()[0]['title'] === 'New expense');
    Queue::assertPushed(CheckExpoPushReceipts::class, fn (CheckExpoPushReceipts $job): bool => $job->receiptTokenIds === ['receipt-123' => $pushToken->id]);
});

it('does not send an event disabled by the user', function () {
    $user = User::factory()->create();
    $pushToken = PushToken::factory()->for($user)->create();
    NotificationPreference::factory()->for($user)->create(['expense_created' => false]);
    Http::preventStrayRequests();

    app(ExpoPushService::class)->sendToUsers(
        [$user->id],
        NotificationType::ExpenseCreated,
        null,
        'New expense',
        'Dinner',
        ['expense_id' => 42],
    );

    Http::assertNothingSent();
    expect($pushToken->fresh()->revoked_at)->toBeNull();
});

it('does not send notifications for a muted group', function () {
    $user = User::factory()->create();
    $group = Group::factory()->for($user, 'creator')->create();
    GroupMember::factory()->owner()->for($group)->for($user)->create([
        'notifications_muted_at' => now(),
    ]);
    PushToken::factory()->for($user)->create();
    Http::preventStrayRequests();

    app(ExpoPushService::class)->sendToUsers(
        [$user->id],
        NotificationType::PaymentReceived,
        $group->id,
        'Payment received',
        'A settlement was recorded.',
        ['group_id' => $group->id],
    );

    Http::assertNothingSent();
});

it('revokes a device rejected by a push ticket', function () {
    $user = User::factory()->create();
    $pushToken = PushToken::factory()->for($user)->create();
    Http::preventStrayRequests();
    Http::fake([
        'https://exp.host/--/api/v2/push/send' => Http::response([
            'data' => [[
                'status' => 'error',
                'message' => 'Device is not registered.',
                'details' => ['error' => 'DeviceNotRegistered'],
            ]],
        ]),
    ]);

    app(ExpoPushService::class)->sendToUsers(
        [$user->id],
        NotificationType::ExpenseCreated,
        null,
        'New expense',
        'Dinner',
        ['expense_id' => 42],
    );

    expect($pushToken->fresh()->revoked_at)->not->toBeNull();
});

it('revokes an unregistered device reported by a push receipt', function () {
    $pushToken = PushToken::factory()->create();
    Http::preventStrayRequests();
    Http::fake([
        'https://exp.host/--/api/v2/push/getReceipts' => Http::response([
            'data' => [
                'receipt-123' => [
                    'status' => 'error',
                    'details' => ['error' => 'DeviceNotRegistered'],
                ],
            ],
        ]),
    ]);

    app(ExpoPushService::class)->checkReceipts(['receipt-123' => $pushToken->id]);

    expect($pushToken->fresh()->revoked_at)->not->toBeNull();
});
