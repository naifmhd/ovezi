<?php

use App\Models\Currency;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

describe('group currency rates', function () {
    it('lets the owner create and update a conversion into the reporting currency', function () {
        $mvr = Currency::factory()->mvr()->create();
        $usd = Currency::factory()->usd()->create();
        $owner = User::factory()->create(['default_currency_code' => $mvr->code]);
        $group = Group::factory()->for($owner, 'creator')->create([
            'reporting_currency_code' => $mvr->code,
        ]);
        GroupMember::factory()->owner()->for($group)->for($owner)->create();
        $token = $owner->createToken('Owner phone');

        $response = $this->withToken($token->plainTextToken)
            ->putJson("/api/v1/groups/{$group->id}/currency-rates/{$usd->code}", [
                'rate' => '15.420000000000',
                'quote_currency_code' => 'USD',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.base_currency_code', $usd->code)
            ->assertJsonPath('data.quote_currency_code', $mvr->code)
            ->assertJsonPath('data.rate', '15.420000000000');
        $this->assertDatabaseHas('activity_logs', [
            'group_id' => $group->id,
            'actor_id' => $owner->id,
            'event' => 'currency_rate.created',
        ]);

        $this->withToken($token->plainTextToken)
            ->putJson("/api/v1/groups/{$group->id}/currency-rates/{$usd->code}", [
                'rate' => '15.500000000000',
            ])
            ->assertOk()
            ->assertJsonPath('data.rate', '15.500000000000');
        $this->assertDatabaseCount('group_currency_rates', 1);
        $this->assertDatabaseHas('activity_logs', ['event' => 'currency_rate.updated']);
    });

    it('allows members to list rates but only owners to manage them', function () {
        $mvr = Currency::factory()->mvr()->create();
        $usd = Currency::factory()->usd()->create();
        $owner = User::factory()->create(['default_currency_code' => $mvr->code]);
        $member = User::factory()->create(['default_currency_code' => $mvr->code]);
        $outsider = User::factory()->create(['default_currency_code' => $mvr->code]);
        $group = Group::factory()->for($owner, 'creator')->create([
            'reporting_currency_code' => $mvr->code,
        ]);
        GroupMember::factory()->owner()->for($group)->for($owner)->create();
        GroupMember::factory()->for($group)->for($member)->create();
        $group->currencyRates()->create([
            'base_currency_code' => $usd->code,
            'quote_currency_code' => $mvr->code,
            'rate' => '15.4',
            'created_by' => $owner->id,
        ]);

        $memberToken = $member->createToken('Member phone');
        $this->withToken($memberToken->plainTextToken)
            ->getJson("/api/v1/groups/{$group->id}/currency-rates")
            ->assertOk()
            ->assertJsonCount(1, 'data');
        $this->withToken($memberToken->plainTextToken)
            ->putJson("/api/v1/groups/{$group->id}/currency-rates/{$usd->code}", ['rate' => '16'])
            ->assertForbidden();

        app('auth')->forgetGuards();
        $outsiderToken = $outsider->createToken('Outsider phone');
        $this->withToken($outsiderToken->plainTextToken)
            ->getJson("/api/v1/groups/{$group->id}/currency-rates")
            ->assertNotFound();
    });

    it('rejects same-currency, invalid, and archived-group overrides', function () {
        $mvr = Currency::factory()->mvr()->create();
        $usd = Currency::factory()->usd()->create();
        $owner = User::factory()->create(['default_currency_code' => $mvr->code]);
        $group = Group::factory()->for($owner, 'creator')->create([
            'reporting_currency_code' => $mvr->code,
        ]);
        GroupMember::factory()->owner()->for($group)->for($owner)->create();
        $token = $owner->createToken('Owner phone');

        $this->withToken($token->plainTextToken)
            ->putJson("/api/v1/groups/{$group->id}/currency-rates/{$mvr->code}", ['rate' => '1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency');
        $this->withToken($token->plainTextToken)
            ->putJson("/api/v1/groups/{$group->id}/currency-rates/{$usd->code}", ['rate' => '0'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rate');

        $group->update(['archived_at' => now()]);
        $this->withToken($token->plainTextToken)
            ->putJson("/api/v1/groups/{$group->id}/currency-rates/{$usd->code}", ['rate' => '15'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('group');
    });

    it('lets the owner delete an override and records the activity', function () {
        $mvr = Currency::factory()->mvr()->create();
        $usd = Currency::factory()->usd()->create();
        $owner = User::factory()->create(['default_currency_code' => $mvr->code]);
        $group = Group::factory()->for($owner, 'creator')->create([
            'reporting_currency_code' => $mvr->code,
        ]);
        GroupMember::factory()->owner()->for($group)->for($owner)->create();
        $group->currencyRates()->create([
            'base_currency_code' => $usd->code,
            'quote_currency_code' => $mvr->code,
            'rate' => '15.4',
            'created_by' => $owner->id,
        ]);
        $token = $owner->createToken('Owner phone');

        $this->withToken($token->plainTextToken)
            ->deleteJson("/api/v1/groups/{$group->id}/currency-rates/{$usd->code}")
            ->assertNoContent();

        $this->assertDatabaseCount('group_currency_rates', 0);
        $this->assertDatabaseHas('activity_logs', ['event' => 'currency_rate.deleted']);
    });
});
