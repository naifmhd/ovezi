<?php

use App\Models\Currency;
use App\Models\Expense;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Placeholder;
use App\Models\User;

it('returns 401 when no access token is provided', function () {
    $this->postJson('/api/v1/expenses')->assertUnauthorized();
    $this->getJson('/api/v1/expenses')->assertUnauthorized();
});

it('lists only expenses visible to the authenticated user', function () {
    $currency = Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => $currency->code]);
    $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($user, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($user)->create();
    $visibleGroupExpense = Expense::factory()->for($group)->create([
        'expense_type' => 'group',
        'payer_user_id' => $user->id,
        'created_by' => $user->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);
    $visiblePersonalExpense = Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $user->id,
        'created_by' => $user->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);
    Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $outsider->id,
        'created_by' => $outsider->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);
    $token = $user->createToken('User phone');

    $response = $this->withToken($token->plainTextToken)->getJson('/api/v1/expenses?per_page=100');

    $response->assertOk()->assertJsonCount(2, 'data');
    expect(collect($response->json('data'))->pluck('id')->all())
        ->toContain($visibleGroupExpense->id, $visiblePersonalExpense->id);
});

it('filters expenses by group and returns calculated splits', function () {
    $currency = Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($user, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($user)->create();
    $token = $user->createToken('User phone');

    $this->withToken($token->plainTextToken)->postJson('/api/v1/expenses', [
        'expense_type' => 'group',
        'group_id' => $group->id,
        'payer_user_id' => $user->id,
        'amount_minor' => 1200,
        'currency_code' => $currency->code,
        'description' => 'Lunch',
        'occurred_at' => '2026-09-18T09:00:00+05:00',
        'split_type' => 'equal',
        'participants' => [['user_id' => $user->id]],
    ])->assertCreated();

    $response = $this->withToken($token->plainTextToken)
        ->getJson("/api/v1/expenses?group_id={$group->id}");

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.description', 'Lunch')
        ->assertJsonPath('data.0.splits.0.amount_owed_minor', 1200);
});

it('shows an expense visible to the authenticated user', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $expense = Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $owner->id,
        'created_by' => $owner->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);

    $this->withToken($owner->createToken('Owner phone')->plainTextToken)
        ->getJson("/api/v1/expenses/{$expense->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $expense->id);
});

it('forbids viewing an expense belonging to someone else', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
    $expense = Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $owner->id,
        'created_by' => $owner->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);

    $this->withToken($outsider->createToken('Outsider phone')->plainTextToken)
        ->getJson("/api/v1/expenses/{$expense->id}")
        ->assertForbidden();
});

it('soft deletes an expense and lets its manager undo within 30 seconds', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $expense = Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $owner->id,
        'created_by' => $owner->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);
    $token = $owner->createToken('Owner phone')->plainTextToken;

    $this->withToken($token)
        ->deleteJson("/api/v1/expenses/{$expense->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    $this->assertDatabaseHas('activity_logs', [
        'subject_id' => $expense->id,
        'event' => 'expense.deleted',
    ]);
    $this->withToken($token)
        ->getJson("/api/v1/expenses/{$expense->id}")
        ->assertNotFound();

    $this->withToken($token)
        ->postJson("/api/v1/expenses/{$expense->id}/restore")
        ->assertOk()
        ->assertJsonPath('data.id', $expense->id);

    $this->assertNotSoftDeleted('expenses', ['id' => $expense->id]);
    $this->assertDatabaseHas('activity_logs', [
        'subject_id' => $expense->id,
        'event' => 'expense.restored',
    ]);
});

it('rejects restoring an expense after the undo window expires', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $expense = Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $owner->id,
        'created_by' => $owner->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);
    $expense->delete();
    $expense->forceFill(['deleted_at' => now()->subSeconds(31)])->saveQuietly();

    $this->withToken($owner->createToken('Owner phone')->plainTextToken)
        ->postJson("/api/v1/expenses/{$expense->id}/restore")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('expense')
        ->assertJsonPath('errors.expense.0', 'The 30-second undo window has expired.');

    $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
});

it('forbids another user from deleting or restoring an expense', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
    $expense = Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $owner->id,
        'created_by' => $owner->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);
    $token = $outsider->createToken('Outsider phone')->plainTextToken;

    $this->withToken($token)
        ->deleteJson("/api/v1/expenses/{$expense->id}")
        ->assertForbidden();

    $expense->delete();

    $this->withToken($token)
        ->postJson("/api/v1/expenses/{$expense->id}/restore")
        ->assertForbidden();
});

it('updates an expense while retaining its captured conversion rate', function () {
    $mvr = Currency::factory()->mvr()->create();
    $usd = Currency::factory()->usd()->create();
    $owner = User::factory()->create(['default_currency_code' => $mvr->code]);
    $expense = Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $owner->id,
        'created_by' => $owner->id,
        'amount_minor' => 1000,
        'currency_code' => $usd->code,
        'reporting_amount_minor' => 15_500,
        'reporting_currency_code' => $mvr->code,
        'exchange_rate' => '15.500000000000',
        'exchange_rate_source' => 'expense',
        'exchange_rate_effective_date' => '2026-09-18',
    ]);

    $response = $this->withToken($owner->createToken('Owner phone')->plainTextToken)
        ->putJson("/api/v1/expenses/{$expense->id}", [
            'expense_type' => 'personal',
            'payer_user_id' => $owner->id,
            'amount_minor' => 2000,
            'currency_code' => $usd->code,
            'description' => 'Updated purchase',
            'category' => 'shopping',
            'occurred_at' => '2026-09-19T09:00:00+05:00',
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.amount_minor', 2000)
        ->assertJsonPath('data.reporting_amount_minor', 31_000)
        ->assertJsonPath('data.exchange_rate', '15.500000000000')
        ->assertJsonPath('data.description', 'Updated purchase');
    $this->assertDatabaseHas('activity_logs', [
        'subject_id' => $expense->id,
        'event' => 'expense.updated',
    ]);
});

it('recalculates a captured conversion rate only when requested', function () {
    $mvr = Currency::factory()->mvr()->create();
    $usd = Currency::factory()->usd()->create();
    $owner = User::factory()->create(['default_currency_code' => $mvr->code]);
    $expense = Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $owner->id,
        'created_by' => $owner->id,
        'amount_minor' => 1000,
        'currency_code' => $usd->code,
        'reporting_amount_minor' => 15_500,
        'reporting_currency_code' => $mvr->code,
        'exchange_rate' => '15.500000000000',
        'exchange_rate_source' => 'expense',
    ]);

    $this->withToken($owner->createToken('Owner phone')->plainTextToken)
        ->putJson("/api/v1/expenses/{$expense->id}", [
            'expense_type' => 'personal',
            'payer_user_id' => $owner->id,
            'amount_minor' => 2000,
            'currency_code' => $usd->code,
            'description' => 'Repriced purchase',
            'occurred_at' => '2026-09-19T09:00:00+05:00',
            'expense_rate' => '16',
            'recalculate_rate' => true,
        ])
        ->assertOk()
        ->assertJsonPath('data.reporting_amount_minor', 32_000)
        ->assertJsonPath('data.exchange_rate', '16.000000000000')
        ->assertJsonPath('data.exchange_rate_source', 'expense');
});

it('replaces group splits atomically when an expense is edited', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $member = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    GroupMember::factory()->for($group)->for($member)->create();
    $token = $owner->createToken('Owner phone')->plainTextToken;
    $created = $this->withToken($token)->postJson('/api/v1/expenses', [
        'expense_type' => 'group',
        'group_id' => $group->id,
        'payer_user_id' => $owner->id,
        'amount_minor' => 1000,
        'currency_code' => $currency->code,
        'description' => 'Dinner',
        'occurred_at' => '2026-09-18T09:00:00+05:00',
        'split_type' => 'equal',
        'participants' => [
            ['user_id' => $owner->id],
            ['user_id' => $member->id],
        ],
    ])->assertCreated();
    $expenseId = $created->json('data.id');

    $response = $this->withToken($token)->putJson("/api/v1/expenses/{$expenseId}", [
        'expense_type' => 'group',
        'group_id' => $group->id,
        'payer_user_id' => $member->id,
        'amount_minor' => 1200,
        'currency_code' => $currency->code,
        'description' => 'Updated dinner',
        'occurred_at' => '2026-09-18T10:00:00+05:00',
        'split_type' => 'exact',
        'participants' => [
            ['user_id' => $owner->id, 'value' => 400],
            ['user_id' => $member->id, 'value' => 800],
        ],
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.payer.user_id', $member->id)
        ->assertJsonPath('data.amount_minor', 1200)
        ->assertJsonPath('data.splits.0.amount_owed_minor', 400)
        ->assertJsonPath('data.splits.1.amount_owed_minor', 800)
        ->assertJsonPath('data.splits.0.split_type', 'exact')
        ->assertJsonCount(2, 'data.splits');
    $this->assertDatabaseCount('expense_splits', 2);
});

it('forbids moving an expense to another type or group during editing', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $expense = Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $owner->id,
        'created_by' => $owner->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);

    $this->withToken($owner->createToken('Owner phone')->plainTextToken)
        ->putJson("/api/v1/expenses/{$expense->id}", [
            'expense_type' => 'direct',
            'payer_user_id' => $owner->id,
            'amount_minor' => 1500,
            'currency_code' => $currency->code,
            'description' => 'Moved expense',
            'occurred_at' => now()->toISOString(),
            'split_type' => 'equal',
            'participants' => [['user_id' => $owner->id]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('expense_type');
});

it('returns 403 when creating an expense in another group', function () {
    $currency = Currency::factory()->mvr()->create();
    $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    $token = $outsider->createToken('Outsider phone');

    $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/expenses', [
        'expense_type' => 'group',
        'group_id' => $group->id,
        'payer_user_id' => $outsider->id,
        'amount_minor' => 1000,
        'currency_code' => $currency->code,
        'description' => 'Private group expense',
        'occurred_at' => '2026-09-18T09:00:00+05:00',
        'split_type' => 'equal',
        'participants' => [
            ['user_id' => $outsider->id],
        ],
    ]);

    $response->assertForbidden();
    $this->assertDatabaseCount('expenses', 0);
});

it('returns 422 with clear messages for invalid expense data', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test phone');

    $response = $this
        ->withToken($token->plainTextToken)
        ->postJson('/api/v1/expenses');

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['expense_type', 'amount_minor', 'currency_code', 'description', 'occurred_at'])
        ->assertJsonPath('errors.expense_type.0', 'The expense type field is required.');
    $this->assertDatabaseCount('expenses', 0);
});

it('returns 422 when exact splits do not equal the expense amount', function () {
    $currency = Currency::factory()->mvr()->create();
    $creator = User::factory()->create(['default_currency_code' => $currency->code]);
    $otherMember = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($creator, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($creator)->create();
    GroupMember::factory()->for($group)->for($otherMember)->create();
    $token = $creator->createToken('Creator phone');

    $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/expenses', [
        'expense_type' => 'group',
        'group_id' => $group->id,
        'payer_user_id' => $creator->id,
        'amount_minor' => 1000,
        'currency_code' => $currency->code,
        'description' => 'Dinner',
        'occurred_at' => '2026-09-18T09:00:00+05:00',
        'split_type' => 'exact',
        'participants' => [
            ['user_id' => $creator->id, 'value' => 600],
            ['user_id' => $otherMember->id, 'value' => 300],
        ],
    ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('participants')
        ->assertJsonPath('errors.participants.0', 'Exact split amounts must equal the expense amount.');
    $this->assertDatabaseCount('expenses', 0);
});

it('creates a group expense and returns its calculated splits', function () {
    $currency = Currency::factory()->mvr()->create();
    $creator = User::factory()->create(['default_currency_code' => $currency->code]);
    $secondMember = User::factory()->create(['default_currency_code' => $currency->code]);
    $thirdMember = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($creator, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($creator)->create();
    GroupMember::factory()->for($group)->for($secondMember)->create();
    GroupMember::factory()->for($group)->for($thirdMember)->create();
    $token = $creator->createToken('Creator phone');

    $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/expenses', [
        'expense_type' => ' GROUP ',
        'group_id' => $group->id,
        'payer_user_id' => $creator->id,
        'amount_minor' => 100,
        'currency_code' => 'mvr',
        'description' => ' Coffee ',
        'category' => ' FOOD ',
        'occurred_at' => '2026-09-18T09:00:00+05:00',
        'split_type' => 'EQUAL',
        'participants' => [
            ['user_id' => $creator->id],
            ['user_id' => $secondMember->id],
            ['user_id' => $thirdMember->id],
        ],
        'created_by' => $thirdMember->id,
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.expense_type', 'group')
        ->assertJsonPath('data.description', 'Coffee')
        ->assertJsonPath('data.category', 'food')
        ->assertJsonPath('data.created_by', $creator->id)
        ->assertJsonPath('data.splits.0.amount_owed_minor', 34)
        ->assertJsonPath('data.splits.0.reporting_amount_owed_minor', 34)
        ->assertJsonCount(3, 'data.splits');

    $this->assertDatabaseHas('expenses', [
        'group_id' => $group->id,
        'created_by' => $creator->id,
        'amount_minor' => 100,
        'description' => 'Coffee',
    ]);
    $this->assertDatabaseCount('expense_splits', 3);
    $this->assertDatabaseCount('activity_logs', 1);
});

it('creates a personal tracking expense without splits', function () {
    $currency = Currency::factory()->mvr()->create();
    $creator = User::factory()->create(['default_currency_code' => $currency->code]);
    $token = $creator->createToken('Creator phone');

    $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/expenses', [
        'expense_type' => 'personal',
        'payer_user_id' => $creator->id,
        'amount_minor' => 2500,
        'currency_code' => $currency->code,
        'description' => 'Groceries',
        'occurred_at' => '2026-09-18T09:00:00+05:00',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.expense_type', 'personal')
        ->assertJsonCount(0, 'data.splits');
    $this->assertDatabaseCount('expense_splits', 0);
});

it('creates a direct expense with a creator-owned placeholder', function () {
    $currency = Currency::factory()->mvr()->create();
    $creator = User::factory()->create(['default_currency_code' => $currency->code]);
    $placeholder = Placeholder::factory()->for($creator, 'creator')->create();
    $token = $creator->createToken('Creator phone');

    $response = $this->withToken($token->plainTextToken)->postJson('/api/v1/expenses', [
        'expense_type' => 'direct',
        'payer_user_id' => $creator->id,
        'amount_minor' => 1500,
        'currency_code' => $currency->code,
        'description' => 'Coffee',
        'occurred_at' => '2026-09-18T09:00:00+05:00',
        'split_type' => 'equal',
        'participants' => [
            ['user_id' => $creator->id],
            ['placeholder_id' => $placeholder->id],
        ],
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.expense_type', 'direct')
        ->assertJsonPath('data.payer.name', $creator->name)
        ->assertJsonPath('data.splits.1.placeholder_id', $placeholder->id)
        ->assertJsonPath('data.splits.1.name', $placeholder->name)
        ->assertJsonPath('data.splits.1.amount_owed_minor', 750);
    $this->assertDatabaseHas('expense_splits', [
        'placeholder_id' => $placeholder->id,
        'amount_owed_minor' => 750,
    ]);
});

it('creates a direct expense with an accepted registered friend', function () {
    $currency = Currency::factory()->mvr()->create();
    $creator = User::factory()->create(['default_currency_code' => $currency->code]);
    $friend = User::factory()->create(['default_currency_code' => $currency->code]);
    Friendship::factory()->accepted()->create([
        'user_id' => min($creator->id, $friend->id),
        'friend_id' => max($creator->id, $friend->id),
        'requested_by' => $creator->id,
    ]);

    $this->withToken($creator->createToken('Creator phone')->plainTextToken)
        ->postJson('/api/v1/expenses', [
            'expense_type' => 'direct',
            'payer_user_id' => $creator->id,
            'amount_minor' => 1500,
            'currency_code' => $currency->code,
            'description' => 'Coffee',
            'occurred_at' => '2026-09-18T09:00:00+05:00',
            'split_type' => 'equal',
            'participants' => [
                ['user_id' => $creator->id],
                ['user_id' => $friend->id],
            ],
        ])
        ->assertCreated()
        ->assertJsonPath('data.splits.1.user_id', $friend->id);
});

it('rejects a registered direct participant who is not an accepted friend', function () {
    $currency = Currency::factory()->mvr()->create();
    $creator = User::factory()->create(['default_currency_code' => $currency->code]);
    $otherUser = User::factory()->create(['default_currency_code' => $currency->code]);

    $this->withToken($creator->createToken('Creator phone')->plainTextToken)
        ->postJson('/api/v1/expenses', [
            'expense_type' => 'direct',
            'payer_user_id' => $creator->id,
            'amount_minor' => 1500,
            'currency_code' => $currency->code,
            'description' => 'Coffee',
            'occurred_at' => '2026-09-18T09:00:00+05:00',
            'split_type' => 'equal',
            'participants' => [
                ['user_id' => $creator->id],
                ['user_id' => $otherUser->id],
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('participants');
});
