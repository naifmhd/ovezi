<?php

use App\Models\Currency;
use App\Models\LendingContact;
use App\Models\LendingLoan;
use App\Models\LendingRepayment;
use App\Models\User;

it('explains the full balance across pages and includes only confirmed allocations for the selected loans', function () {
    Currency::factory()->mvr()->create();
    Currency::factory()->create(['code' => 'USD']);
    $user = User::factory()->create();
    $person = LendingContact::factory()->create(['owner_id' => $user->id]);
    $loans = LendingLoan::factory()->count(31)->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'amount_minor' => 1000]);
    $loans[0]->update(['repaid_minor' => 200]);
    $loans[1]->update(['repaid_minor' => 100]);
    $payment = LendingRepayment::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'amount_minor' => 300]);
    $payment->loans()->attach([$loans[0]->id => ['amount_minor' => 200], $loans[1]->id => ['amount_minor' => 100]]);
    foreach (['pending', 'reversed', 'rejected'] as $status) {
        $other = LendingRepayment::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'status' => $status]);
        $other->loans()->attach($loans[0]->id, ['amount_minor' => 100]);
    }
    foreach ([['currency_code' => 'USD'], ['direction' => 'borrowed'], ['voided_at' => now()]] as $override) {
        LendingLoan::factory()->create([...['lending_contact_id' => $person->id, 'currency_code' => 'MVR'], ...$override]);
    }
    $url = "/api/v1/lending/contacts/{$person->id}/balance?currency_code=MVR&direction=lent";
    $this->actingAs($user)->getJson($url)->assertOk()->assertJsonCount(30, 'data')->assertJsonPath('meta.total', 31)
        ->assertJsonPath('summary.amount_minor', 31000)->assertJsonPath('summary.repaid_minor', 300)->assertJsonPath('summary.outstanding_minor', 30700);
    $this->getJson($url.'&page=2')->assertJsonCount(1, 'data')->assertJsonPath('summary.outstanding_minor', 30700);
    $this->getJson($url.'&view=repayments')->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.amount_minor', 100)->assertJsonPath('data.1.amount_minor', 200)->assertJsonPath('summary.repaid_minor', 300);
});

it('restricts a shared balance to visible loans and their allocated repayment portions using the viewer direction', function () {
    Currency::factory()->mvr()->create();
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $contact = LendingContact::factory()->create(['owner_id' => $owner->id, 'connected_user_id' => $viewer->id]);
    $shared = LendingLoan::factory()->create(['lending_contact_id' => $contact->id, 'currency_code' => 'MVR', 'shared_with' => $viewer->id, 'amount_minor' => 500, 'repaid_minor' => 100]);
    $private = LendingLoan::factory()->create(['lending_contact_id' => $contact->id, 'currency_code' => 'MVR', 'amount_minor' => 500, 'repaid_minor' => 200]);
    $payment = LendingRepayment::factory()->create(['lending_contact_id' => $contact->id, 'currency_code' => 'MVR', 'amount_minor' => 300]);
    $payment->loans()->attach([$shared->id => ['amount_minor' => 100], $private->id => ['amount_minor' => 200]]);
    $url = "/api/v1/lending/contacts/{$contact->id}/balance?currency_code=MVR&direction=borrowed";
    $this->actingAs($viewer)->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.direction', 'borrowed')->assertJsonPath('summary.outstanding_minor', 400)->assertJsonPath('person_name', $owner->name);
    $this->getJson($url.'&view=repayments')->assertJsonCount(1, 'data')->assertJsonPath('data.0.amount_minor', 100)->assertJsonMissing(['loan_id' => $private->id]);
    $this->getJson(str_replace('direction=borrowed', 'direction=lent', $url))->assertJsonCount(0, 'data')->assertJsonPath('summary.outstanding_minor', 0);
    $this->actingAs(User::factory()->create())->getJson($url)->assertNotFound();
});

it('finds same-person duplicate loans within one day without including other currencies directions or voided loans', function () {
    $this->travelTo(now()->setDate(2026, 10, 15));
    Currency::factory()->mvr()->create();
    Currency::factory()->create(['code' => 'USD']);
    $user = User::factory()->create();
    $contact = LendingContact::factory()->create(['owner_id' => $user->id]);
    $base = ['lending_contact_id' => $contact->id, 'amount_minor' => 500, 'currency_code' => 'MVR', 'occurred_on' => '2026-10-13'];
    foreach (['2026-10-12', '2026-10-13', '2026-10-14'] as $date) {
        LendingLoan::factory()->create([...$base, 'occurred_on' => $date]);
    }
    foreach ([['occurred_on' => '2026-10-15'], ['amount_minor' => 501], ['currency_code' => 'USD'], ['direction' => 'borrowed'], ['voided_at' => now()]] as $override) {
        LendingLoan::factory()->create([...$base, ...$override]);
    }
    $payload = ['contact_id' => $contact->id, 'amount_minor' => 500, 'currency_code' => 'MVR', 'direction' => 'lent', 'occurred_on' => '2026-10-13'];
    $this->actingAs($user)->postJson('/api/v1/lending/loans/duplicates', $payload)->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('total', 3);
    $this->assertDatabaseCount('lending_loans', 8);
    $this->assertDatabaseCount('lending_contacts', 1);
});

it('checks existing private contacts by normalized name or email for inline entry without creating a person', function () {
    Currency::factory()->mvr()->create();
    $user = User::factory()->create();
    $contact = LendingContact::factory()->create(['owner_id' => $user->id, 'name' => 'Aishath', 'email_hash' => hash('sha256', 'aishath@example.test')]);
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $contact->id, 'currency_code' => 'MVR']);
    $stranger = LendingContact::factory()->create(['name' => 'Aishath']);
    LendingLoan::factory()->create(['lending_contact_id' => $stranger->id, 'currency_code' => 'MVR']);
    $base = ['amount_minor' => 10000, 'currency_code' => 'MVR', 'direction' => 'lent', 'occurred_on' => now()->toDateString()];
    $this->actingAs($user)->postJson('/api/v1/lending/loans/duplicates', [...$base, 'person' => ['name' => ' AISHATH ']])->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $loan->id);
    $this->postJson('/api/v1/lending/loans/duplicates', [...$base, 'person' => ['name' => 'Different name', 'email' => 'AISHATH@example.test']])->assertJsonPath('total', 1);
    $this->postJson('/api/v1/lending/loans/duplicates', [...$base, 'person' => ['name' => 'New person']])->assertJsonCount(0, 'data');
    $this->postJson('/api/v1/lending/loans/duplicates', [...$base, 'contact_id' => $stranger->id])->assertNotFound();
    $this->assertDatabaseCount('lending_contacts', 2);
});

it('checks shared duplicates in viewer direction without leaking unshared loans', function () {
    Currency::factory()->mvr()->create();
    $viewer = User::factory()->create();
    $contact = LendingContact::factory()->create(['connected_user_id' => $viewer->id]);
    LendingLoan::factory()->create(['lending_contact_id' => $contact->id, 'currency_code' => 'MVR', 'shared_with' => $viewer->id]);
    LendingLoan::factory()->create(['lending_contact_id' => $contact->id, 'currency_code' => 'MVR']);
    $payload = ['contact_id' => $contact->id, 'currency_code' => 'MVR', 'amount_minor' => 10000, 'direction' => 'borrowed', 'occurred_on' => now()->toDateString()];
    $this->actingAs($viewer)->postJson('/api/v1/lending/loans/duplicates', $payload)->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.direction', 'borrowed');
    $this->postJson('/api/v1/lending/loans/duplicates', [...$payload, 'direction' => 'lent'])->assertJsonPath('total', 0);
});

it('authenticates and validates balance and duplicate queries', function () {
    $contact = LendingContact::factory()->create();
    $this->getJson("/api/v1/lending/contacts/{$contact->id}/balance")->assertUnauthorized();
    $this->postJson('/api/v1/lending/loans/duplicates', [])->assertUnauthorized();
    $this->actingAs($contact->owner)->getJson("/api/v1/lending/contacts/{$contact->id}/balance?currency_code=BAD&direction=bad&view=bad&page=0")->assertUnprocessable()->assertJsonValidationErrors(['currency_code', 'direction', 'view', 'page']);
    $this->postJson('/api/v1/lending/loans/duplicates', ['contact_id' => $contact->id])->assertUnprocessable()->assertJsonValidationErrors(['amount_minor', 'currency_code', 'occurred_on']);
});
