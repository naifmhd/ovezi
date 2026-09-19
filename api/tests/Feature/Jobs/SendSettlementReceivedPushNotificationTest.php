<?php

use App\Jobs\CheckExpoPushReceipts;
use App\Jobs\SendSettlementReceivedPushNotification;
use App\Models\Currency;
use App\Models\PushToken;
use App\Models\Settlement;
use App\Models\User;
use App\Services\ExpoPushService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('sends a direct-payment notification without requiring a group', function () {
    $currency = Currency::factory()->mvr()->create();
    $sender = User::factory()->create([
        'name' => 'Naila',
        'default_currency_code' => $currency->code,
    ]);
    $recipient = User::factory()->create(['default_currency_code' => $currency->code]);
    $recipientToken = PushToken::factory()->for($recipient)->create();
    $settlement = Settlement::factory()->create([
        'group_id' => null,
        'from_user_id' => $sender->id,
        'to_user_id' => $recipient->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
        'created_by' => $sender->id,
    ]);
    Queue::fake([CheckExpoPushReceipts::class]);
    Http::preventStrayRequests();
    Http::fake([
        'https://exp.host/--/api/v2/push/send' => Http::response([
            'data' => [['status' => 'ok', 'id' => 'receipt-direct']],
        ]),
    ]);

    (new SendSettlementReceivedPushNotification($settlement->id))->handle(app(ExpoPushService::class));

    Http::assertSent(fn (Request $request): bool => $request->data()[0]['to'] === $recipientToken->expo_push_token
        && $request->data()[0]['body'] === 'Naila recorded a payment to you.'
        && $request->data()[0]['data']['path'] === '/(app)/(tabs)');
    Queue::assertPushed(CheckExpoPushReceipts::class);
});
