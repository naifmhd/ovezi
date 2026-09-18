<?php

use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Placeholder;
use App\Models\User;

it('accepts one participant reference', function () {
    $split = ExpenseSplit::factory()->create();

    $this->assertModelExists($split);
    expect($split->amount_owed_minor)->toBe(750);
    expect($split->placeholder_id)->toBeNull();
});

it('rejects multiple participant references', function () {
    $expense = Expense::factory()->create();
    $user = User::factory()->create();
    $placeholder = Placeholder::factory()->create();

    expect(fn () => ExpenseSplit::factory()->create([
        'expense_id' => $expense->id,
        'user_id' => $user->id,
        'placeholder_id' => $placeholder->id,
    ]))->toThrow(InvalidArgumentException::class, 'exactly one user or placeholder');
});
