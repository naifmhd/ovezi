<?php

use App\Models\Currency;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\LendingContact;
use App\Models\LendingLoan;
use App\Models\User;

it('pins groups before pagination only for the requesting member and preserves hidden filtering', function () {
    Currency::factory()->mvr()->create();
    $user = User::factory()->create();
    $other = User::factory()->create();
    $groups = Group::factory()->count(2)->create(['reporting_currency_code' => 'MVR']);
    foreach ($groups as $group) {
        GroupMember::factory()->for($group)->for($user)->create();
        GroupMember::factory()->for($group)->for($other)->create();
    }
    $first = $groups->first();
    $url = "/api/v1/groups/{$first->id}/pin";
    $this->actingAs($user)->putJson($url, ['pinned' => true])->assertOk()->assertJsonPath('data.is_pinned', true);
    $this->putJson($url, ['pinned' => true])->assertOk();
    $this->assertDatabaseCount('user_pins', 1);
    $this->getJson('/api/v1/groups?status=active&per_page=1')->assertOk()->assertJsonPath('data.0.id', $first->id)->assertJsonPath('data.0.is_pinned', true);
    GroupMember::where('user_id', $user->id)->where('group_id', $first->id)->update(['hidden_at' => now()]);
    $this->getJson('/api/v1/groups?status=active')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $groups->last()->id);
    $this->actingAs($other)->getJson('/api/v1/groups?status=active&per_page=1')->assertJsonPath('data.0.id', $groups->last()->id)->assertJsonPath('data.0.is_pinned', false);
    $this->actingAs($user)->putJson($url, ['pinned' => false])->assertOk();
    $this->assertDatabaseCount('user_pins', 0);
});

it('keeps shared person pins independent and sorts them before the contact page boundary', function () {
    Currency::factory()->mvr()->create();
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $first = LendingContact::factory()->create(['owner_id' => $owner->id, 'connected_user_id' => $recipient->id]);
    LendingLoan::factory()->create(['lending_contact_id' => $first->id, 'currency_code' => 'MVR', 'shared_with' => $recipient->id, 'shared_at' => now()]);
    LendingContact::factory()->count(31)->create(['owner_id' => $owner->id]);
    $url = "/api/v1/lending/contacts/{$first->id}/pin";
    $this->actingAs($owner)->putJson($url, ['pinned' => true])->assertOk();
    $this->getJson('/api/v1/lending/contacts')->assertOk()->assertJsonPath('data.0.id', $first->id)->assertJsonPath('data.0.is_pinned', true)->assertJsonPath('meta.total', 32);
    $this->actingAs($recipient)->getJson('/api/v1/lending/contacts')->assertOk()->assertJsonPath('data.0.is_pinned', false);
    $this->putJson($url, ['pinned' => true])->assertOk();
    $this->putJson($url, ['pinned' => false])->assertOk();
    $this->actingAs($owner)->getJson('/api/v1/lending/contacts')->assertJsonPath('data.0.is_pinned', true);
});

it('rejects unauthorized pins and validates pin state', function () {
    $contact = LendingContact::factory()->create();
    $group = Group::factory()->create();
    $this->putJson("/api/v1/lending/contacts/{$contact->id}/pin", ['pinned' => true])->assertUnauthorized();
    $this->actingAs(User::factory()->create())->putJson("/api/v1/lending/contacts/{$contact->id}/pin", ['pinned' => true])->assertNotFound();
    $this->putJson("/api/v1/groups/{$group->id}/pin", ['pinned' => true])->assertNotFound();
    $this->actingAs($contact->owner)->putJson("/api/v1/lending/contacts/{$contact->id}/pin", ['pinned' => 'maybe'])->assertUnprocessable()->assertJsonValidationErrors('pinned');
    $this->assertDatabaseCount('user_pins', 0);
});
