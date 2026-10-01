<?php

use App\Jobs\GenerateRecurringExpenseOccurrences;
use App\Jobs\SendExpenseCreatedPushNotifications;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Placeholder;
use App\Models\RecurringExpense;
use App\Models\User;
use App\Services\GroupBalanceCalculator;
use Illuminate\Support\Facades\Queue;

function contributionContext(): array
{
    Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => 'MVR']);
    $friend = User::factory()->create(['default_currency_code' => 'MVR']);
    Friendship::factory()->accepted()->create(['user_id' => $user->id, 'friend_id' => $friend->id]);
    $input = [
        'expense_type' => 'direct', 'payer_user_id' => $user->id,
        'amount_minor' => 10000, 'currency_code' => 'MVR', 'description' => 'Shared dinner',
        'occurred_at' => '2026-10-01T12:00:00Z', 'split_type' => 'equal',
        'participants' => [
            ['user_id' => $user->id, 'amount_paid_minor' => 7000],
            ['user_id' => $friend->id, 'amount_paid_minor' => 3000],
        ],
    ];

    return [$user, $friend, $input];
}

it('credits two payers separately and records only the net one-to-one debt', function () {
    [$user, $friend, $input] = contributionContext();
    Queue::fake([SendExpenseCreatedPushNotifications::class]);

    $response = $this->actingAs($user)->postJson('/api/v1/expenses', $input);

    $response->assertCreated()->assertJsonPath('data.splits.0.amount_paid_minor', 7000)
        ->assertJsonPath('data.splits.1.amount_paid_minor', 3000)
        ->assertJsonPath('data.splits.0.amount_owed_minor', 5000);
    $this->assertDatabaseHas('expense_splits', ['expense_id' => $response->json('data.id'), 'user_id' => $friend->id, 'reporting_amount_paid_minor' => 3000]);
    $this->getJson('/api/v1/balances')->assertJsonPath('data.direct.0.balance_minor', 2000);
    $this->actingAs($friend)->getJson('/api/v1/balances')->assertJsonPath('data.direct.0.balance_minor', -2000);
    Queue::assertPushed(SendExpenseCreatedPushNotifications::class, fn ($job) => $job->expenseId === $response->json('data.id'));
});

it('allows paying entirely for a friend without the payer owing a share', function () {
    [$user, $friend, $input] = contributionContext();
    $input['participants'] = [['user_id' => $friend->id]];

    $response = $this->actingAs($user)->postJson('/api/v1/expenses', $input);

    $response->assertCreated()->assertJsonPath('data.splits.0.amount_owed_minor', 10000)
        ->assertJsonPath('data.splits.1.included_in_split', false)
        ->assertJsonPath('data.splits.1.amount_owed_minor', 0);
    $this->getJson('/api/v1/balances')->assertJsonPath('data.direct.0.balance_minor', 10000);
});

it('allows recording an expense for a friend alone with no balance for its creator', function () {
    [$user, $friend, $input] = contributionContext();
    $input['payer_user_id'] = $friend->id;
    $input['participants'] = [['user_id' => $friend->id]];

    $this->actingAs($user)->postJson('/api/v1/expenses', $input)->assertCreated();

    $this->getJson('/api/v1/balances')->assertJsonCount(0, 'data.direct');
});

it('rejects an unrelated payer even when that payer is excluded from the split', function () {
    [$user, $friend, $input] = contributionContext();
    $outsider = User::factory()->create();
    $input['payer_user_id'] = $outsider->id;
    $input['participants'] = [['user_id' => $friend->id]];

    $this->actingAs($user)->postJson('/api/v1/expenses', $input)->assertUnprocessable()->assertJsonValidationErrors('participants');

    $this->assertDatabaseCount('expenses', 0);
});

it('rejects invalid contribution totals and split membership without saving a debt', function (string $invalid) {
    [$user, $friend, $input] = contributionContext();
    if ($invalid === 'too much') {
        $input['participants'][1]['amount_paid_minor'] = 4000;
    }
    if ($invalid === 'too little') {
        $input['participants'][1]['amount_paid_minor'] = 2000;
    }
    if ($invalid === 'negative') {
        $input['participants'][1]['amount_paid_minor'] = -100;
    }
    if ($invalid === 'missing') {
        unset($input['participants'][1]['amount_paid_minor']);
    }
    if ($invalid === 'duplicate') {
        $input['participants'][1]['user_id'] = $user->id;
    }
    if ($invalid === 'none included') {
        $input['participants'] = array_map(fn ($p) => [...$p, 'included_in_split' => false], $input['participants']);
    }

    $this->actingAs($user)->postJson('/api/v1/expenses', $input)->assertUnprocessable();

    $this->assertDatabaseCount('expenses', 0);
})->with(['too much', 'too little', 'negative', 'missing', 'duplicate', 'none included']);

it('recalculates both sides when shared payments are edited and removes their impact on deletion', function () {
    [$user, $friend, $input] = contributionContext();
    $id = $this->actingAs($user)->postJson('/api/v1/expenses', $input)->assertCreated()->json('data.id');
    $input['participants'][0]['amount_paid_minor'] = 4000;
    $input['participants'][1]['amount_paid_minor'] = 6000;

    $this->putJson("/api/v1/expenses/{$id}", $input)->assertOk();

    $this->getJson('/api/v1/balances')->assertJsonPath('data.direct.0.balance_minor', -1000);
    $this->deleteJson("/api/v1/expenses/{$id}")->assertNoContent();
    $this->getJson('/api/v1/balances')->assertJsonCount(0, 'data.direct');
});

it('protects multiple payments from edits submitted by older clients', function () {
    [$user, $friend, $input] = contributionContext();
    $id = $this->actingAs($user)->postJson('/api/v1/expenses', $input)->assertCreated()->json('data.id');
    $input['participants'] = [['user_id' => $user->id], ['user_id' => $friend->id]];

    $this->putJson("/api/v1/expenses/{$id}", $input)->assertUnprocessable()->assertJsonValidationErrors('participants');

    $this->assertDatabaseHas('expense_splits', ['expense_id' => $id, 'user_id' => $user->id, 'amount_paid_minor' => 7000]);
});

it('keeps group balances conserved when multiple payments require currency rounding', function () {
    [$user, $friend, $input] = contributionContext();
    Currency::factory()->usd()->create();
    $group = Group::factory()->for($user, 'creator')->create(['reporting_currency_code' => 'MVR']);
    GroupMember::factory()->owner()->for($group)->for($user)->create();
    GroupMember::factory()->for($group)->for($friend)->create();
    $input = [...$input, 'expense_type' => 'group', 'group_id' => $group->id, 'currency_code' => 'USD', 'expense_rate' => '15.42', 'amount_minor' => 101,
        'participants' => [['user_id' => $user->id, 'amount_paid_minor' => 60], ['user_id' => $friend->id, 'amount_paid_minor' => 41]]];

    $response = $this->actingAs($user)->postJson('/api/v1/expenses', $input)->assertCreated();

    $splits = collect($response->json('data.splits'));
    expect($splits->sum('reporting_amount_paid_minor'))->toBe($response->json('data.reporting_amount_minor'));
    expect($splits->sum('reporting_amount_owed_minor'))->toBe($response->json('data.reporting_amount_minor'));
    expect(app(GroupBalanceCalculator::class)->calculate($group))->toBe(["user:{$user->id}" => 138, "user:{$friend->id}" => -138]);
});

it('preserves shared payments through recurring creation editing and generation', function () {
    [$user, $friend, $input] = contributionContext();
    $this->travelTo(now()->setDate(2026, 10, 1)->startOfDay());
    $input['frequency'] = 'weekly';
    $response = $this->actingAs($user)->postJson('/api/v1/recurring-expenses', $input)->assertCreated();
    $schedule = RecurringExpense::query()->sole();
    expect($schedule->splits->pluck('amount_paid_minor')->all())->toBe([7000, 3000]);
    $input['description'] = 'Weekly shared dinner';
    $this->putJson("/api/v1/recurring-expenses/{$schedule->id}", $input)->assertOk();
    $this->travelTo(now()->setDate(2026, 10, 8)->endOfDay());

    app()->call([new GenerateRecurringExpenseOccurrences($schedule->id), 'handle']);

    $expense = Expense::query()->latest('id')->firstOrFail();
    expect($expense->description)->toBe('Weekly shared dinner');
    expect($expense->splits->pluck('amount_paid_minor')->all())->toBe([7000, 3000]);
    $this->assertDatabaseCount('expenses', 2);
});

it('includes payer-only guests after claiming their account without changing the net debt', function () {
    [$user, $friend, $input] = contributionContext();
    $guest = Placeholder::factory()->for($user, 'creator')->create(['claimed_by' => $friend->id]);
    $input['participants'][1] = ['placeholder_id' => $guest->id, 'amount_paid_minor' => 3000, 'included_in_split' => false];

    $this->actingAs($user)->postJson('/api/v1/expenses', $input)->assertCreated();

    $this->getJson('/api/v1/balances')->assertJsonPath('data.direct.0.participant.user_id', $friend->id)->assertJsonPath('data.direct.0.balance_minor', -3000);
});

it('filters friend expenses without exposing another accounts direct history', function () {
    [$user, $friend, $input] = contributionContext();
    $id = $this->actingAs($user)->postJson('/api/v1/expenses', $input)->assertCreated()->json('data.id');
    $outsider = User::factory()->create();

    $this->getJson("/api/v1/expenses?friend_id={$friend->id}")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
    $this->actingAs($outsider)->getJson("/api/v1/expenses?friend_id={$friend->id}")->assertOk()->assertJsonCount(0, 'data');
});

it('settles the net debt from two payments without charging the original share again', function () {
    [$user, $friend, $input] = contributionContext();
    $this->actingAs($user)->postJson('/api/v1/expenses', $input)->assertCreated();

    $this->actingAs($friend)->postJson('/api/v1/settlements', [
        'from_user_id' => $friend->id, 'to_user_id' => $user->id, 'amount_minor' => 2000,
        'currency_code' => 'MVR', 'reporting_currency_code' => 'MVR', 'occurred_at' => '2026-10-02T12:00:00Z',
    ])->assertCreated();

    $this->getJson('/api/v1/balances')->assertJsonCount(0, 'data.direct');
    $this->actingAs($user)->getJson('/api/v1/balances')->assertJsonCount(0, 'data.direct');
    $this->assertDatabaseHas('settlements', ['from_user_id' => $friend->id, 'to_user_id' => $user->id, 'amount_minor' => 2000]);
});
