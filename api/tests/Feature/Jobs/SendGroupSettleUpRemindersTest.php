<?php

use App\ExpenseType;
use App\Jobs\CheckExpoPushReceipts;
use App\Jobs\SendGroupSettleUpReminders;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\PushToken;
use App\Models\User;
use App\Services\ExpoPushService;
use App\Services\GroupBalanceCalculator;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

it('reminds only active users who owe money in the group', function () {
    $currency = Currency::factory()->mvr()->create();
    $creditor = User::factory()->create(['default_currency_code' => $currency->code]);
    $debtor = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($creditor, 'creator')->create([
        'name' => 'Malé Weekend',
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($creditor)->create();
    GroupMember::factory()->for($group)->for($debtor)->create();
    $expense = Expense::factory()->for($creditor, 'creator')->create([
        'expense_type' => ExpenseType::Group,
        'group_id' => $group->id,
        'payer_user_id' => $creditor->id,
        'amount_minor' => 1000,
        'currency_code' => $currency->code,
        'reporting_amount_minor' => 1000,
        'reporting_currency_code' => $currency->code,
    ]);
    ExpenseSplit::factory()->for($expense)->for($creditor)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);
    ExpenseSplit::factory()->for($expense)->for($debtor)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);
    $creditorToken = PushToken::factory()->for($creditor)->create();
    $debtorToken = PushToken::factory()->for($debtor)->create();
    Queue::fake([CheckExpoPushReceipts::class]);
    Http::preventStrayRequests();
    Http::fake([
        'https://exp.host/--/api/v2/push/send' => Http::response([
            'data' => [['status' => 'ok', 'id' => 'receipt-123']],
        ]),
    ]);

    (new SendGroupSettleUpReminders($group->id))->handle(
        app(GroupBalanceCalculator::class),
        app(ExpoPushService::class),
    );

    Http::assertSent(fn (Request $request): bool => $request->data()[0]['to'] === $debtorToken->expo_push_token
        && $request->data()[0]['body'] === 'You owe MVR 5.00 in Malé Weekend.');
    Http::assertNotSent(fn (Request $request): bool => $request->data()[0]['to'] === $creditorToken->expo_push_token);
    Queue::assertPushed(CheckExpoPushReceipts::class);
});

it('does not send a reminder when every balance is settled', function () {
    $currency = Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($user, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($user)->create();
    PushToken::factory()->for($user)->create();
    Http::preventStrayRequests();

    (new SendGroupSettleUpReminders($group->id))->handle(
        app(GroupBalanceCalculator::class),
        app(ExpoPushService::class),
    );

    Http::assertNothingSent();
});
