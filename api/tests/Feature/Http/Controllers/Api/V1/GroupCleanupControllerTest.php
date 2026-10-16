<?php

use App\Models\Currency;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\RecurringExpense;
use App\Models\User;

function cleanupFixture(): array
{
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $member = User::factory()->create(['default_currency_code' => $currency->code]);
    $group = Group::factory()->create(['created_by' => $owner->id, 'reporting_currency_code' => $currency->code]);
    GroupMember::factory()->owner()->create(['group_id' => $group->id, 'user_id' => $owner->id]);
    GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $member->id]);

    return [$owner, $member, $group];
}

it('hides groups only for the current member and can show them again', function () {
    [$owner, $member, $group] = cleanupFixture();
    $this->actingAs($member)->putJson("/api/v1/groups/{$group->id}/hidden", ['hidden' => true])->assertOk();
    $this->getJson('/api/v1/groups?status=active')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/groups?status=hidden')->assertJsonPath('data.0.id', $group->id);
    $this->actingAs($owner)->getJson('/api/v1/groups?status=active')->assertJsonCount(1, 'data');
    $this->actingAs($member)->putJson("/api/v1/groups/{$group->id}/hidden", ['hidden' => false])->assertOk();
    $this->getJson('/api/v1/groups?status=active')->assertJsonCount(1, 'data');
});

it('allows only the owner to delete and restore while preserving members', function () {
    [$owner, $member, $group] = cleanupFixture();
    $this->actingAs($member)->deleteJson("/api/v1/groups/{$group->id}")->assertForbidden();
    $this->actingAs($owner)->deleteJson("/api/v1/groups/{$group->id}")->assertOk();
    $this->assertSoftDeleted('groups', ['id' => $group->id]);
    $this->assertDatabaseCount('group_members', 2);
    $this->getJson('/api/v1/groups?status=deleted')->assertJsonPath('data.0.id', $group->id);
    $this->actingAs($member)->getJson('/api/v1/groups?status=deleted')->assertJsonCount(0, 'data');
    $this->postJson("/api/v1/groups/{$group->id}/restore")->assertForbidden();
    $this->actingAs($owner)->postJson("/api/v1/groups/{$group->id}/restore")->assertOk()->assertJsonPath('data.is_archived', true);
    expect($group->fresh()->deleted_at)->toBeNull();
});

it('rejects deletion when any member owes money and allows deletion after settlement', function () {
    [$owner, $member, $group] = cleanupFixture();
    $this->actingAs($owner)->postJson('/api/v1/expenses', [
        'expense_type' => 'group', 'group_id' => $group->id, 'payer_user_id' => $owner->id,
        'amount_minor' => 1000, 'currency_code' => 'MVR', 'description' => 'Dinner',
        'occurred_at' => now()->toIso8601String(), 'split_type' => 'equal',
        'participants' => [['user_id' => $owner->id], ['user_id' => $member->id]],
    ])->assertCreated();
    $this->deleteJson("/api/v1/groups/{$group->id}")->assertUnprocessable()->assertJsonValidationErrors('group');
    $this->postJson("/api/v1/groups/{$group->id}/settlements", ['from_user_id' => $member->id, 'to_user_id' => $owner->id, 'amount_minor' => 500, 'currency_code' => 'MVR', 'occurred_at' => now()->toIso8601String()])->assertCreated();
    $this->deleteJson("/api/v1/groups/{$group->id}")->assertOk();
    $this->assertDatabaseCount('expenses', 1);
    $this->assertDatabaseCount('settlements', 1);
});

it('rejects deletion with paused recurring schedules and rejects writes after deletion', function () {
    [$owner, $member, $group] = cleanupFixture();
    $schedule = RecurringExpense::factory()->create([
        'expense_type' => 'group', 'group_id' => $group->id, 'created_by' => $owner->id,
        'payer_user_id' => $owner->id, 'currency_code' => 'MVR', 'paused_at' => now(), 'next_occurrence_on' => now()->addMonth(),
    ]);
    $this->actingAs($owner)->deleteJson("/api/v1/groups/{$group->id}")->assertUnprocessable();
    $schedule->update(['canceled_at' => now(), 'next_occurrence_on' => null]);
    $this->deleteJson("/api/v1/groups/{$group->id}")->assertOk();
    $this->putJson("/api/v1/groups/{$group->id}/archive")->assertNotFound();
    $this->postJson("/api/v1/groups/{$group->id}/settlements", [])->assertNotFound();
});
