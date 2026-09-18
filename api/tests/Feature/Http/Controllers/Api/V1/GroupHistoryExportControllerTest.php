<?php

use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Settlement;
use App\Models\User;

it('exports active and deleted group history as csv', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['name' => 'Naif', 'default_currency_code' => $currency->code]);
    $friend = User::factory()->create(['name' => 'Sarah', 'default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'name' => 'Malé Weekend',
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();
    GroupMember::factory()->for($group)->for($friend)->create();
    $expense = Expense::factory()->for($group)->create([
        'expense_type' => 'group',
        'payer_user_id' => $owner->id,
        'created_by' => $owner->id,
        'amount_minor' => 2400,
        'currency_code' => $currency->code,
        'reporting_amount_minor' => 2400,
        'reporting_currency_code' => $currency->code,
        'description' => 'Harbour lunch',
        'occurred_at' => '2026-09-18 07:00:00',
    ]);
    ExpenseSplit::factory()->for($expense)->for($owner)->create([
        'amount_owed_minor' => 1200,
        'reporting_amount_owed_minor' => 1200,
    ]);
    ExpenseSplit::factory()->for($expense)->for($friend)->create([
        'amount_owed_minor' => 1200,
        'reporting_amount_owed_minor' => 1200,
    ]);
    Settlement::factory()->for($group)->create([
        'from_user_id' => $friend->id,
        'to_user_id' => $owner->id,
        'created_by' => $friend->id,
        'amount_minor' => 1200,
        'currency_code' => $currency->code,
        'reporting_amount_minor' => 1200,
        'reporting_currency_code' => $currency->code,
        'method' => 'bank_transfer',
        'note' => '=HYPERLINK("https://example.com","Paid")',
        'occurred_at' => '2026-09-19 07:00:00',
    ]);
    $expense->delete();

    $response = $this->withToken($friend->createToken('Friend phone')->plainTextToken)
        ->get("/api/v1/groups/{$group->id}/export");

    $response
        ->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8')
        ->assertHeader('content-disposition', 'attachment; filename="male-weekend-history.csv"');

    $csv = $response->getContent();
    expect($csv)
        ->toContain('record_type,status,occurred_at')
        ->toContain('expense,deleted')
        ->toContain('Harbour lunch')
        ->toContain('Naif: 12.00 MVR')
        ->toContain('Sarah: 12.00 MVR')
        ->toContain('settlement,active')
        ->toContain("'=HYPERLINK")
        ->toContain('bank_transfer');
});

it('does not export a group to a non-member', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $outsider = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    GroupMember::factory()->owner()->for($group)->for($owner)->create();

    $this->withToken($outsider->createToken('Outsider phone')->plainTextToken)
        ->get("/api/v1/groups/{$group->id}/export")
        ->assertNotFound();
});
