<?php

use App\ExpenseType;
use App\Jobs\SendSettlementReceivedPushNotification;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Friendship;
use App\Models\Placeholder;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

/** @return array{currency: Currency, creditor: User, debtor: User} */
function directSettlementScenario(): array
{
    $currency = Currency::factory()->mvr()->create();
    $creditor = User::factory()->create(['default_currency_code' => $currency->code]);
    $debtor = User::factory()->create(['default_currency_code' => $currency->code]);
    Friendship::factory()->accepted()->create([
        'user_id' => min($creditor->id, $debtor->id),
        'friend_id' => max($creditor->id, $debtor->id),
        'requested_by' => $creditor->id,
    ]);
    $expense = Expense::factory()->create([
        'expense_type' => ExpenseType::Direct,
        'group_id' => null,
        'payer_user_id' => $creditor->id,
        'amount_minor' => 1000,
        'currency_code' => $currency->code,
        'reporting_amount_minor' => 1000,
        'reporting_currency_code' => $currency->code,
        'created_by' => $debtor->id,
    ]);
    ExpenseSplit::factory()->for($expense)->for($creditor)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);
    ExpenseSplit::factory()->for($expense)->for($debtor)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);

    return compact('currency', 'creditor', 'debtor');
}

it('records a direct payment and reduces only that pair currency balance', function () {
    ['currency' => $currency, 'creditor' => $creditor, 'debtor' => $debtor] = directSettlementScenario();
    Queue::fake([SendSettlementReceivedPushNotification::class]);

    $response = $this->actingAs($debtor)->postJson('/api/v1/settlements', [
        'from_user_id' => $debtor->id,
        'to_user_id' => $creditor->id,
        'amount_minor' => 300,
        'currency_code' => strtolower($currency->code),
        'reporting_currency_code' => strtolower($currency->code),
        'method' => ' Bank Transfer ',
        'note' => ' Paid outside Ovezi ',
        'occurred_at' => '2026-09-18T12:00:00+05:00',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.group_id', null)
        ->assertJsonPath('data.reporting_amount_minor', 300)
        ->assertJsonPath('data.method', 'bank transfer')
        ->assertJsonPath('data.note', 'Paid outside Ovezi');
    $this->assertDatabaseHas('settlements', [
        'group_id' => null,
        'from_user_id' => $debtor->id,
        'to_user_id' => $creditor->id,
        'reporting_amount_minor' => 300,
    ]);
    $this->assertDatabaseHas('activity_logs', [
        'group_id' => null,
        'actor_id' => $debtor->id,
        'event' => 'settlement.created',
    ]);
    Queue::assertPushed(SendSettlementReceivedPushNotification::class);

    $this->actingAs($debtor)
        ->getJson('/api/v1/balances')
        ->assertOk()
        ->assertJsonPath('data.direct.0.participant.user_id', $creditor->id)
        ->assertJsonPath('data.direct.0.currency_code', $currency->code)
        ->assertJsonPath('data.direct.0.balance_minor', -200);
});

it('rejects overpayment and a registered counterpart who is not an accepted friend', function () {
    ['currency' => $currency, 'creditor' => $creditor, 'debtor' => $debtor] = directSettlementScenario();
    $payload = [
        'from_user_id' => $debtor->id,
        'to_user_id' => $creditor->id,
        'amount_minor' => 501,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
        'occurred_at' => now()->toISOString(),
    ];

    $this->actingAs($debtor)
        ->postJson('/api/v1/settlements', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount_minor');

    $stranger = User::factory()->create(['default_currency_code' => $currency->code]);
    $payload['to_user_id'] = $stranger->id;
    $payload['amount_minor'] = 100;

    $this->actingAs($debtor)
        ->postJson('/api/v1/settlements', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('settlement');
    $this->assertDatabaseCount('settlements', 0);
});

it('records a direct payment to a placeholder owned by the user', function () {
    $currency = Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => $currency->code]);
    $placeholder = Placeholder::factory()->for($user, 'creator')->create(['name' => 'Aisha']);
    $expense = Expense::factory()->create([
        'expense_type' => ExpenseType::Direct,
        'payer_placeholder_id' => $placeholder->id,
        'payer_user_id' => null,
        'amount_minor' => 1000,
        'currency_code' => $currency->code,
        'reporting_amount_minor' => 1000,
        'reporting_currency_code' => $currency->code,
        'created_by' => $user->id,
    ]);
    ExpenseSplit::factory()->for($expense)->for($user)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);
    ExpenseSplit::factory()->for($expense)->create([
        'user_id' => null,
        'placeholder_id' => $placeholder->id,
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);

    $this->actingAs($user)->postJson('/api/v1/settlements', [
        'from_user_id' => $user->id,
        'to_placeholder_id' => $placeholder->id,
        'amount_minor' => 500,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
        'occurred_at' => now()->toISOString(),
    ])->assertCreated();

    $this->actingAs($user)
        ->getJson('/api/v1/balances')
        ->assertOk()
        ->assertJsonCount(0, 'data.direct');
});
