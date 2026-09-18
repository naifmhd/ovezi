<?php

use App\ExpenseType;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

test('group members can view while creators and owners can manage an expense', function () {
    $owner = User::factory()->create();
    $creator = User::factory()->create();
    $member = User::factory()->create();
    $outsider = User::factory()->create();
    $group = Group::factory()->for($owner, 'creator')->create();
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    GroupMember::factory()->for($group)->for($creator)->create();
    GroupMember::factory()->for($group)->for($member)->create();
    $expense = Expense::factory()->create([
        'expense_type' => ExpenseType::Group,
        'group_id' => $group->id,
        'payer_user_id' => $creator->id,
        'currency_code' => $group->reporting_currency_code,
        'reporting_currency_code' => $group->reporting_currency_code,
        'created_by' => $creator->id,
    ]);

    expect($owner->can('view', $expense))->toBeTrue();
    expect($owner->can('update', $expense))->toBeTrue();
    expect($creator->can('update', $expense))->toBeTrue();
    expect($member->can('view', $expense))->toBeTrue();
    expect($member->can('update', $expense))->toBeFalse();
    expect($outsider->can('view', $expense))->toBeFalse();
});

test('direct expenses are visible to their participants only', function () {
    $creator = User::factory()->create();
    $participant = User::factory()->create();
    $outsider = User::factory()->create();
    $expense = Expense::factory()->create([
        'payer_user_id' => $creator->id,
        'created_by' => $creator->id,
    ]);
    ExpenseSplit::factory()->for($expense)->for($participant)->create();

    expect($creator->can('view', $expense))->toBeTrue();
    expect($participant->can('view', $expense))->toBeTrue();
    expect($participant->can('update', $expense))->toBeFalse();
    expect($outsider->can('view', $expense))->toBeFalse();
});

test('personal expenses are private to their creator', function () {
    $creator = User::factory()->create();
    $outsider = User::factory()->create();
    $expense = Expense::factory()->create([
        'expense_type' => ExpenseType::Personal,
        'payer_user_id' => $creator->id,
        'created_by' => $creator->id,
    ]);

    expect($creator->can('view', $expense))->toBeTrue();
    expect($outsider->can('view', $expense))->toBeFalse();
});
