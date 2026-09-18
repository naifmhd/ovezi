<?php

use App\Models\Currency;
use App\Models\Expense;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

it('searches only groups, expenses, and accepted friends visible to the user', function () {
    $currency = Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => $currency->code]);
    $friend = User::factory()->create(['name' => 'Lagoon Friend']);
    $outsider = User::factory()->create();
    Friendship::factory()->accepted()->create([
        'user_id' => min($user->id, $friend->id),
        'friend_id' => max($user->id, $friend->id),
    ]);
    $visibleGroup = Group::factory()->for($user, 'creator')->create([
        'name' => 'Lagoon Holiday',
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($visibleGroup)->for($user)->create();
    $hiddenGroup = Group::factory()->for($outsider, 'creator')->create([
        'name' => 'Lagoon Secret',
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($hiddenGroup)->for($outsider)->create();
    $visibleExpense = Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $user->id,
        'created_by' => $user->id,
        'description' => 'Lagoon ferry',
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);
    Expense::factory()->create([
        'expense_type' => 'personal',
        'payer_user_id' => $outsider->id,
        'created_by' => $outsider->id,
        'description' => 'Lagoon private dinner',
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
    ]);

    $response = $this->withToken($user->createToken('User phone')->plainTextToken)
        ->getJson('/api/v1/search?q=lagoon')
        ->assertOk()
        ->assertJsonCount(1, 'data.groups')
        ->assertJsonCount(1, 'data.expenses')
        ->assertJsonCount(1, 'data.friends')
        ->assertJsonPath('data.groups.0.id', $visibleGroup->id)
        ->assertJsonPath('data.expenses.0.id', $visibleExpense->id)
        ->assertJsonPath('data.friends.0.id', $friend->id);

    expect(collect($response->json('data.groups'))->pluck('id'))->not->toContain($hiddenGroup->id);
});

it('requires at least two search characters', function () {
    $user = User::factory()->create();

    $this->withToken($user->createToken('User phone')->plainTextToken)
        ->getJson('/api/v1/search?q=a')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('q');
});

it('requires authentication to search', function () {
    $this->getJson('/api/v1/search?q=group')->assertUnauthorized();
});
