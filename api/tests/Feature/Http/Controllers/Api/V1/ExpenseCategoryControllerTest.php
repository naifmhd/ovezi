<?php

use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('requires authentication for the category library', function () {
    $this->getJson('/api/v1/expense-categories')->assertUnauthorized();
    $this->postJson('/api/v1/expense-categories', ['name' => 'Health'])->assertUnauthorized();
    $this->patchJson('/api/v1/expense-categories/1', ['archived' => true])->assertUnauthorized();
});

it('normalizes categories and rejects duplicate or invalid names', function () {
    $this->actingAs(User::factory()->create());
    $this->postJson('/api/v1/expense-categories', ['name' => '  School   fees  '])->assertCreated()->assertJsonPath('data.name', 'School fees');
    foreach (['school FEES', 'FOOD', '', str_repeat('x', 51)] as $name) {
        $this->postJson('/api/v1/expense-categories', ['name' => $name])->assertUnprocessable()->assertJsonValidationErrors('name');
    }
    $this->assertDatabaseCount('expense_categories', 1);
    $this->getJson('/api/v1/expense-categories')->assertOk()->assertJsonCount(6, 'data');
});

it('isolates each users categories and recently used labels', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $id = $this->actingAs($owner)->postJson('/api/v1/expense-categories', ['name' => 'Health'])->assertCreated()->json('data.id');
    Expense::factory()->create(['created_by' => $owner->id, 'category' => 'health']);
    $this->getJson('/api/v1/expense-categories')->assertJsonPath('recent.0', 'health');
    $this->actingAs($other)->getJson('/api/v1/expense-categories')->assertJsonCount(5, 'data')->assertJsonCount(0, 'recent');
    $this->patchJson("/api/v1/expense-categories/{$id}", ['name' => 'Stolen'])->assertNotFound();
    $this->patchJson("/api/v1/expense-categories/{$id}", ['archived' => true])->assertNotFound();
    $this->postJson('/api/v1/expense-categories', ['name' => 'Health'])->assertCreated();
});

it('renames archives and restores categories without rewriting expense history', function () {
    $owner = User::factory()->create();
    $id = $this->actingAs($owner)->postJson('/api/v1/expense-categories', ['name' => 'Health'])->assertCreated()->json('data.id');
    $expense = Expense::factory()->create(['created_by' => $owner->id, 'category' => 'health']);
    $url = "/api/v1/expense-categories/{$id}";
    $this->patchJson($url, ['name' => '  Medical  '])->assertOk()->assertJsonPath('data.name', 'Medical');
    $this->patchJson($url, ['archived' => true])->assertOk();
    expect(DB::table('expense_categories')->find($id)->archived_at)->not->toBeNull();
    $this->postJson('/api/v1/expense-categories', ['name' => 'medical'])->assertUnprocessable();
    $this->patchJson($url, ['archived' => false])->assertOk()->assertJsonPath('data.archived_at', null);
    $this->patchJson($url, ['name' => 'Food'])->assertUnprocessable();
    $this->patchJson($url, ['archived' => 'maybe'])->assertUnprocessable();
    expect($expense->fresh()->category)->toBe('health');
});

it('allows clearing an expense category without changing its amount', function () {
    $user = User::factory()->create();
    $expense = Expense::factory()->create(['expense_type' => 'personal', 'created_by' => $user->id, 'payer_user_id' => $user->id, 'category' => 'health']);
    $this->actingAs($user)->patchJson("/api/v1/expenses/{$expense->id}", [
        'expense_type' => 'personal', 'payer_user_id' => $user->id, 'amount_minor' => $expense->amount_minor,
        'currency_code' => $expense->currency_code, 'description' => $expense->description,
        'occurred_at' => $expense->occurred_at->toIso8601String(), 'category' => '',
    ])->assertOk()->assertJsonPath('data.category', null)->assertJsonPath('data.amount_minor', $expense->amount_minor);
});
