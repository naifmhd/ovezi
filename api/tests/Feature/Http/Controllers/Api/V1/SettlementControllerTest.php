<?php

use App\ExpenseType;
use App\Jobs\SendSettlementReceivedPushNotification;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

/** @return array{currency: Currency, group: Group, creditor: User, debtor: User} */
function settlementTestScenario(array $groupAttributes = []): array
{
    $currency = Currency::factory()->mvr()->create();
    $creditor = User::factory()->create(['default_currency_code' => $currency->code]);
    $debtor = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($creditor, 'creator')->create([
        'reporting_currency_code' => $currency->code,
        ...$groupAttributes,
    ]);
    GroupMember::factory()->owner()->for($group)->for($creditor)->create();
    GroupMember::factory()->for($group)->for($debtor)->create();
    $expense = Expense::factory()->create([
        'expense_type' => ExpenseType::Group,
        'group_id' => $group->id,
        'payer_user_id' => $creditor->id,
        'amount_minor' => 1000,
        'currency_code' => $currency->code,
        'reporting_amount_minor' => 1000,
        'reporting_currency_code' => $currency->code,
        'created_by' => $creditor->id,
    ]);
    ExpenseSplit::factory()->for($expense)->for($creditor)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);
    ExpenseSplit::factory()->for($expense)->for($debtor)->create([
        'amount_owed_minor' => 500,
        'reporting_amount_owed_minor' => 500,
    ]);

    return compact('currency', 'group', 'creditor', 'debtor');
}

it('records an external payment and updates group balances', function () {
    ['currency' => $currency, 'group' => $group, 'creditor' => $creditor, 'debtor' => $debtor] = settlementTestScenario();
    $token = $debtor->createToken('Debtor phone');
    Queue::fake([SendSettlementReceivedPushNotification::class]);

    $response = $this->withToken($token->plainTextToken)
        ->postJson("/api/v1/groups/{$group->id}/settlements", [
            'from_user_id' => $debtor->id,
            'to_user_id' => $creditor->id,
            'amount_minor' => 300,
            'currency_code' => strtolower($currency->code),
            'method' => ' Bank Transfer ',
            'note' => ' Paid outside Ovezi ',
            'occurred_at' => '2026-09-18T12:00:00+05:00',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.from.user_id', $debtor->id)
        ->assertJsonPath('data.to.user_id', $creditor->id)
        ->assertJsonPath('data.reporting_amount_minor', 300)
        ->assertJsonPath('data.method', 'bank transfer')
        ->assertJsonPath('data.note', 'Paid outside Ovezi');
    $this->assertDatabaseHas('activity_logs', [
        'group_id' => $group->id,
        'actor_id' => $debtor->id,
        'event' => 'settlement.created',
    ]);
    $settlementId = $response->json('data.id');
    Queue::assertPushed(
        SendSettlementReceivedPushNotification::class,
        fn (SendSettlementReceivedPushNotification $job): bool => $job->settlementId === $settlementId,
    );

    $balances = $this->withToken($token->plainTextToken)
        ->getJson("/api/v1/groups/{$group->id}/balances")
        ->assertOk();
    $balances->assertJsonFragment(['balance_minor' => 200]);
    $balances->assertJsonFragment(['balance_minor' => -200]);
});

it('rejects overpayment and unrelated recorders', function () {
    ['currency' => $currency, 'group' => $group, 'creditor' => $creditor, 'debtor' => $debtor] = settlementTestScenario();
    $otherMember = User::factory()->create(['default_currency_code' => $currency->code]);
    GroupMember::factory()->for($group)->for($otherMember)->create();
    $payload = [
        'from_user_id' => $debtor->id,
        'to_user_id' => $creditor->id,
        'amount_minor' => 501,
        'currency_code' => $currency->code,
        'occurred_at' => now()->toISOString(),
    ];

    $debtorToken = $debtor->createToken('Debtor phone');
    $this->withToken($debtorToken->plainTextToken)
        ->postJson("/api/v1/groups/{$group->id}/settlements", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('amount_minor');

    app('auth')->forgetGuards();
    $otherToken = $otherMember->createToken('Other phone');
    $payload['amount_minor'] = 100;
    $this->withToken($otherToken->plainTextToken)
        ->postJson("/api/v1/groups/{$group->id}/settlements", $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('settlement');
});

it('allows settlement while the group is archived', function () {
    ['currency' => $currency, 'group' => $group, 'creditor' => $creditor, 'debtor' => $debtor] = settlementTestScenario([
        'archived_at' => now(),
    ]);
    $token = $creditor->createToken('Owner phone');

    $this->withToken($token->plainTextToken)
        ->postJson("/api/v1/groups/{$group->id}/settlements", [
            'from_user_id' => $debtor->id,
            'to_user_id' => $creditor->id,
            'amount_minor' => 500,
            'currency_code' => $currency->code,
            'occurred_at' => now()->toISOString(),
        ])
        ->assertCreated();
});

it('validates distinct sender and recipient participants', function () {
    ['currency' => $currency, 'group' => $group, 'debtor' => $debtor] = settlementTestScenario();
    $token = $debtor->createToken('Debtor phone');

    $this->withToken($token->plainTextToken)
        ->postJson("/api/v1/groups/{$group->id}/settlements", [
            'from_user_id' => $debtor->id,
            'to_user_id' => $debtor->id,
            'amount_minor' => 100,
            'currency_code' => $currency->code,
            'occurred_at' => now()->toISOString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('to_user_id');
});

it('replays a lost settlement response without repeating balances activity or notifications', function () {
    ['currency' => $currency, 'group' => $group, 'creditor' => $creditor, 'debtor' => $debtor] = settlementTestScenario();
    Queue::fake([SendSettlementReceivedPushNotification::class]);
    $input = ['from_user_id' => $debtor->id, 'to_user_id' => $creditor->id, 'amount_minor' => 300,
        'currency_code' => $currency->code, 'reporting_currency_code' => $currency->code, 'occurred_at' => now()->toISOString()];
    $this->withToken($debtor->createToken('phone')->plainTextToken)->withHeader('Idempotency-Key', (string) Str::uuid());
    $first = $this->postJson("/api/v1/groups/{$group->id}/settlements", $input)->assertCreated();
    $this->postJson("/api/v1/groups/{$group->id}/settlements", $input)->assertCreated()->assertHeader('Idempotency-Replayed', 'true')->assertExactJson($first->json());
    $this->assertDatabaseCount('settlements', 1);
    expect(ActivityLog::query()->where('event', 'settlement.created')->count())->toBe(1);
    Queue::assertPushed(SendSettlementReceivedPushNotification::class, 1);
});
