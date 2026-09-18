<?php

use App\Actions\CreateExpense;
use App\Exceptions\InvalidSplit;
use App\ExpenseType;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\SplitType;
use Carbon\CarbonImmutable;

beforeEach(function () {
    Currency::factory()->mvr()->create();
});

it('creates a group expense with balanced splits and activity atomically', function () {
    $creator = User::factory()->create(['default_currency_code' => 'MVR']);
    $secondMember = User::factory()->create(['default_currency_code' => 'MVR']);
    $thirdMember = User::factory()->create(['default_currency_code' => 'MVR']);
    $group = Group::factory()->create([
        'created_by' => $creator->id,
        'reporting_currency_code' => 'MVR',
    ]);

    foreach ([$creator, $secondMember, $thirdMember] as $member) {
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $member->id,
        ]);
    }

    $expense = app(CreateExpense::class)->execute($creator, [
        'expense_type' => ExpenseType::Group,
        'group' => $group,
        'payer_user_id' => $creator->id,
        'amount_minor' => 100,
        'currency_code' => 'MVR',
        'description' => 'Coffee',
        'occurred_at' => CarbonImmutable::parse('2026-09-18 09:00:00'),
        'split_type' => SplitType::Equal,
        'participants' => [
            ['user_id' => $creator->id, 'value' => 1],
            ['user_id' => $secondMember->id, 'value' => 1],
            ['user_id' => $thirdMember->id, 'value' => 1],
        ],
    ]);

    expect($expense->reporting_amount_minor)->toBe(100)
        ->and($expense->splits->pluck('amount_owed_minor', 'user_id')->all())->toBe([
            $creator->id => 34,
            $secondMember->id => 33,
            $thirdMember->id => 33,
        ])
        ->and(ActivityLog::query()->whereMorphedTo('subject', $expense)->where('event', 'expense.created')->exists())
        ->toBeTrue();
});

it('captures an expense-specific conversion rate', function () {
    Currency::factory()->usd()->create();
    $creator = User::factory()->create(['default_currency_code' => 'MVR']);
    $otherMember = User::factory()->create(['default_currency_code' => 'MVR']);
    $group = Group::factory()->create([
        'created_by' => $creator->id,
        'reporting_currency_code' => 'MVR',
    ]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $creator->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $otherMember->id]);

    $expense = app(CreateExpense::class)->execute($creator, [
        'expense_type' => ExpenseType::Group,
        'group' => $group,
        'payer_user_id' => $creator->id,
        'amount_minor' => 1000,
        'currency_code' => 'USD',
        'description' => 'Dinner',
        'occurred_at' => CarbonImmutable::parse('2026-09-18'),
        'split_type' => SplitType::Equal,
        'participants' => [
            ['user_id' => $creator->id, 'value' => 1],
            ['user_id' => $otherMember->id, 'value' => 1],
        ],
        'expense_rate' => '15.420000000000',
    ]);

    expect($expense->exchange_rate)->toBe('15.420000000000')
        ->and($expense->reporting_amount_minor)->toBe(15420)
        ->and($expense->activities()->firstOrFail()->metadata)->toMatchArray([
            'exchange_rate' => '15.420000000000',
            'exchange_rate_source' => 'expense',
        ]);
});

it('creates a personal tracking expense without balance splits', function () {
    $creator = User::factory()->create(['default_currency_code' => 'MVR']);

    $expense = app(CreateExpense::class)->execute($creator, [
        'expense_type' => ExpenseType::Personal,
        'payer_user_id' => $creator->id,
        'amount_minor' => 2500,
        'currency_code' => 'MVR',
        'description' => 'Groceries',
        'occurred_at' => CarbonImmutable::parse('2026-09-18'),
    ]);

    expect($expense->splits)->toBeEmpty()
        ->and($expense->group_id)->toBeNull();
});

it('does not persist anything when split validation fails', function () {
    $creator = User::factory()->create(['default_currency_code' => 'MVR']);
    $otherMember = User::factory()->create(['default_currency_code' => 'MVR']);
    $group = Group::factory()->create([
        'created_by' => $creator->id,
        'reporting_currency_code' => 'MVR',
    ]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $creator->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $otherMember->id]);

    expect(fn () => app(CreateExpense::class)->execute($creator, [
        'expense_type' => ExpenseType::Group,
        'group' => $group,
        'payer_user_id' => $creator->id,
        'amount_minor' => 100,
        'currency_code' => 'MVR',
        'description' => 'Invalid dinner',
        'occurred_at' => CarbonImmutable::parse('2026-09-18'),
        'split_type' => SplitType::Exact,
        'participants' => [
            ['user_id' => $creator->id, 'value' => 60],
            ['user_id' => $otherMember->id, 'value' => 30],
        ],
    ]))->toThrow(InvalidSplit::class);

    expect(Expense::query()->count())->toBe(0)
        ->and(ExpenseSplit::query()->count())->toBe(0)
        ->and(ActivityLog::query()->count())->toBe(0);
});
