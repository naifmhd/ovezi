<?php

use App\Jobs\CheckExpoPushReceipts;
use App\Jobs\SendExpenseCreatedPushNotifications;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\PushToken;
use App\Models\User;
use App\Services\ExpoPushService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('notifies affected teammates but not the creator or uninvolved group members', function () {
    $currency = Currency::factory()->mvr()->create();
    $creator = User::factory()->create(['default_currency_code' => $currency->code]);
    $participant = User::factory()->create(['default_currency_code' => $currency->code]);
    $uninvolvedMember = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($creator, 'creator')->create([
        'name' => 'Weekend trip',
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($creator)->create();
    GroupMember::factory()->for($group)->for($participant)->create();
    GroupMember::factory()->for($group)->for($uninvolvedMember)->create();
    $creatorToken = PushToken::factory()->for($creator)->create();
    $participantToken = PushToken::factory()->for($participant)->create();
    $uninvolvedToken = PushToken::factory()->for($uninvolvedMember)->create();
    $expense = Expense::factory()->for($group)->create([
        'expense_type' => 'group',
        'payer_user_id' => $creator->id,
        'created_by' => $creator->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
        'description' => 'Dinner',
    ]);
    ExpenseSplit::factory()->for($expense)->for($creator)->create();
    ExpenseSplit::factory()->for($expense)->for($participant)->create();

    Queue::fake([CheckExpoPushReceipts::class]);
    Http::preventStrayRequests();
    Http::fake([
        'https://exp.host/--/api/v2/push/send' => Http::response([
            'data' => [['status' => 'ok', 'id' => 'receipt-participant']],
        ]),
    ]);

    (new SendExpenseCreatedPushNotifications($expense->id))
        ->handle(app(ExpoPushService::class));

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => count($request->data()) === 1
        && $request->data()[0]['to'] === $participantToken->expo_push_token
        && $request->data()[0]['title'] === "{$creator->name} added an expense"
        && $request->data()[0]['body'] === 'Dinner in Weekend trip'
        && $request->data()[0]['data']['expense_id'] === $expense->id);
    Http::assertNotSent(fn (Request $request): bool => collect($request->data())->contains(
        fn (array $message): bool => in_array($message['to'], [
            $creatorToken->expo_push_token,
            $uninvolvedToken->expo_push_token,
        ], true),
    ));
    Queue::assertPushed(
        CheckExpoPushReceipts::class,
        fn (CheckExpoPushReceipts $job): bool => $job->receiptTokenIds === [
            'receipt-participant' => $participantToken->id,
        ],
    );
});
