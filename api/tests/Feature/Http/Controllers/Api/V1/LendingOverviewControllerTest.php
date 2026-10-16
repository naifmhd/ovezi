<?php

use App\Models\Currency;
use App\Models\LendingContact;
use App\Models\LendingLoan;
use App\Models\LendingRepayment;
use App\Models\User;
use Illuminate\Support\Str;

it('requires authentication for overview and attention', function () {
    $this->getJson('/api/v1/lending/overview')->assertUnauthorized();
    $this->getJson('/api/v1/lending/attention?kind=overdue')->assertUnauthorized();
});

it('totals all visible loans by currency and viewer direction without exposing other private loans', function () {
    Currency::factory()->mvr()->create();
    Currency::factory()->create(['code' => 'USD']);
    $user = User::factory()->create();
    $own = LendingContact::factory()->create(['owner_id' => $user->id]);
    $other = LendingContact::factory()->create(['connected_user_id' => $user->id]);
    LendingLoan::factory()->count(31)->create(['lending_contact_id' => $own->id, 'currency_code' => 'MVR', 'amount_minor' => 100, 'repaid_minor' => 20]);
    LendingLoan::factory()->create(['lending_contact_id' => $own->id, 'currency_code' => 'USD', 'amount_minor' => 300]);
    LendingLoan::factory()->create(['lending_contact_id' => $own->id, 'currency_code' => 'MVR', 'amount_minor' => 999, 'voided_at' => now()]);
    LendingLoan::factory()->create(['lending_contact_id' => $other->id, 'currency_code' => 'MVR', 'amount_minor' => 500, 'shared_with' => $user->id, 'shared_at' => now()]);
    LendingLoan::factory()->create(['lending_contact_id' => $other->id, 'currency_code' => 'USD', 'amount_minor' => 999]);

    $this->actingAs($user)->getJson('/api/v1/lending/overview')->assertOk()->assertJsonPath('data.balances', [
        ['currency_code' => 'MVR', 'direction' => 'borrowed', 'amount_minor' => 500, 'repaid_minor' => 0, 'outstanding_minor' => 500],
        ['currency_code' => 'MVR', 'direction' => 'lent', 'amount_minor' => 3100, 'repaid_minor' => 620, 'outstanding_minor' => 2480],
        ['currency_code' => 'USD', 'direction' => 'lent', 'amount_minor' => 300, 'repaid_minor' => 0, 'outstanding_minor' => 300],
    ]);
});

it('shows pending repayments only to the lender and acknowledgement only to the recipient', function () {
    Currency::factory()->mvr()->create();
    $lender = User::factory()->create();
    $borrower = User::factory()->create();
    $person = LendingContact::factory()->create(['owner_id' => $lender->id, 'connected_user_id' => $borrower->id]);
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'created_by' => $lender->id, 'currency_code' => 'MVR', 'shared_with' => $borrower->id, 'shared_at' => now(), 'acknowledgement' => 'pending']);
    $payment = LendingRepayment::factory()->create(['lending_contact_id' => $person->id, 'recorded_by' => $borrower->id, 'currency_code' => 'MVR', 'status' => 'pending']);
    $payment->loans()->attach($loan->id, ['amount_minor' => 100]);

    $this->actingAs($lender)->getJson('/api/v1/lending/overview')->assertJsonPath('data.attention_counts.repayments', 1)->assertJsonPath('data.attention_counts.acknowledgements', 0);
    $this->getJson('/api/v1/lending/attention?kind=repayments')->assertJsonPath('data.0.id', $loan->id)->assertJsonPath('data.0.pending_minor', 100);
    $this->actingAs($borrower)->getJson('/api/v1/lending/overview')->assertJsonPath('data.attention_counts.repayments', 0)->assertJsonPath('data.attention_counts.acknowledgements', 1);
    $this->getJson('/api/v1/lending/attention?kind=repayments')->assertJsonCount(0, 'data');
});

it('paginates attention records and excludes settled, voided, today and private loans', function () {
    Currency::factory()->mvr()->create();
    $user = User::factory()->create();
    $person = LendingContact::factory()->create(['owner_id' => $user->id]);
    LendingLoan::factory()->count(31)->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'due_on' => '2026-10-14']);
    foreach ([['repaid_minor' => 10000], ['voided_at' => now()], ['due_on' => '2026-10-15']] as $override) {
        LendingLoan::factory()->create([...['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'due_on' => '2026-10-14'], ...$override]);
    }
    LendingLoan::factory()->create(['currency_code' => 'MVR', 'due_on' => '2026-10-14']);

    $this->actingAs($user)->getJson('/api/v1/lending/overview?today=2026-10-15')->assertJsonPath('data.attention_counts.overdue', 31);
    $this->getJson('/api/v1/lending/attention?kind=overdue&today=2026-10-15')->assertJsonCount(30, 'data')->assertJsonPath('last_page', 2);
    $this->getJson('/api/v1/lending/attention?kind=overdue&today=2026-10-15&page=2')->assertJsonCount(1, 'data')->assertJsonPath('data.0.person_name', $person->name);
    $this->getJson('/api/v1/lending/attention?kind=invalid')->assertUnprocessable()->assertJsonValidationErrors('kind');
});

it('counts live invitations for a verified matching email only', function () {
    $this->freezeTime();
    $user = User::factory()->create();
    $attributes = ['email_hash' => hash('sha256', mb_strtolower($user->email)), 'invitation_status' => 'pending', 'invitation_expires_at' => now()->addDay()];
    LendingContact::factory()->create($attributes);
    LendingContact::factory()->create([...$attributes, 'invitation_expires_at' => now()->subSecond()]);
    LendingContact::factory()->create([...$attributes, 'invitation_status' => 'declined']);

    $this->actingAs($user)->getJson('/api/v1/lending/overview')->assertJsonPath('data.attention_counts.invitations', 1);
    $user->forceFill(['email_verified_at' => null])->save();
    $this->getJson('/api/v1/lending/overview')->assertJsonPath('data.attention_counts.invitations', 0);
});

it('creates a person and loan atomically and safely replays a retried submission', function () {
    Currency::factory()->mvr()->create();
    $user = User::factory()->create();
    $payload = ['person' => ['name' => 'Aishath', 'email' => 'aishath@example.test'], 'direction' => 'lent', 'amount_minor' => 12550, 'currency_code' => 'MVR', 'occurred_on' => now()->toDateString()];
    $key = (string) Str::uuid();

    $response = $this->actingAs($user)->withHeader('Idempotency-Key', $key)->postJson('/api/v1/lending/loans', $payload)->assertCreated()->assertJsonPath('data.amount_minor', 12550)->assertJsonPath('data.sharing', 'private');
    $this->postJson('/api/v1/lending/loans', $payload)->assertCreated()->assertJsonPath('data.id', $response->json('data.id'));
    $this->assertDatabaseCount('lending_contacts', 1);
    $this->assertDatabaseCount('lending_loans', 1);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/lending/loans', [...$payload, 'shared' => true])->assertUnprocessable();
    $this->assertDatabaseCount('lending_contacts', 1);
});

it('rejects an inaccessible person and ambiguous or incomplete quick entry without creating records', function () {
    Currency::factory()->mvr()->create();
    $user = User::factory()->create();
    $stranger = LendingContact::factory()->create();
    $payload = ['direction' => 'lent', 'amount_minor' => 100, 'currency_code' => 'MVR', 'occurred_on' => now()->toDateString()];

    $this->actingAs($user)->postJson('/api/v1/lending/loans', [...$payload, 'contact_id' => $stranger->id])->assertNotFound();
    $this->postJson('/api/v1/lending/loans', [...$payload, 'contact_id' => $stranger->id, 'person' => ['name' => 'Other']])->assertUnprocessable();
    $this->postJson('/api/v1/lending/loans', [...$payload, 'person' => ['email' => 'invalid']])->assertUnprocessable()->assertJsonValidationErrors(['person.name', 'person.email']);
    $this->assertDatabaseCount('lending_contacts', 1);
    $this->assertDatabaseCount('lending_loans', 0);
});

it('surfaces disputes to both participants and corrections only to the other participant', function () {
    Currency::factory()->mvr()->create();
    $owner = User::factory()->create();
    $recipient = User::factory()->create();
    $person = LendingContact::factory()->create(['owner_id' => $owner->id, 'connected_user_id' => $recipient->id]);
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'shared_with' => $recipient->id, 'shared_at' => now(), 'acknowledgement' => 'disputed', 'void_requested_by' => $owner->id]);

    $this->actingAs($owner)->getJson('/api/v1/lending/overview')->assertJsonPath('data.attention_counts.disputed', 1)->assertJsonPath('data.attention_counts.corrections', 0);
    $this->actingAs($recipient)->getJson('/api/v1/lending/overview')->assertJsonPath('data.attention_counts.disputed', 1)->assertJsonPath('data.attention_counts.corrections', 1);
    $this->getJson('/api/v1/lending/attention?kind=corrections')->assertJsonPath('data.0.id', $loan->id)->assertJsonPath('data.0.person_name', $owner->name);
    $this->actingAs(User::factory()->create())->getJson('/api/v1/lending/attention?kind=disputed')->assertJsonCount(0, 'data');
});

it('reverses attention and balances when the contact owner is the borrower', function () {
    Currency::factory()->mvr()->create();
    $owner = User::factory()->create();
    $lender = User::factory()->create();
    $person = LendingContact::factory()->create(['owner_id' => $owner->id, 'connected_user_id' => $lender->id]);
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'direction' => 'borrowed', 'shared_with' => $lender->id, 'shared_at' => now()]);
    $payment = LendingRepayment::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'direction' => 'borrowed', 'status' => 'pending']);
    $payment->loans()->attach($loan->id, ['amount_minor' => 100]);

    $this->actingAs($owner)->getJson('/api/v1/lending/overview')->assertJsonPath('data.balances.0.direction', 'borrowed')->assertJsonPath('data.attention_counts.repayments', 0);
    $this->actingAs($lender)->getJson('/api/v1/lending/overview')->assertJsonPath('data.balances.0.direction', 'lent')->assertJsonPath('data.attention_counts.repayments', 1);
});

it('lists due soon from today through seven days ahead and excludes overdue settled voided and private loans', function () {
    Currency::factory()->mvr()->create();
    $user = User::factory()->create();
    $person = LendingContact::factory()->create(['owner_id' => $user->id]);
    foreach (['2026-10-14', '2026-10-15', '2026-10-22', '2026-10-23', null] as $date) {
        LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'due_on' => $date]);
    }
    LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'due_on' => '2026-10-15', 'repaid_minor' => 10000]);
    LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'due_on' => '2026-10-15', 'voided_at' => now()]);
    LendingLoan::factory()->create(['currency_code' => 'MVR', 'due_on' => '2026-10-15']);
    $this->actingAs($user)->getJson('/api/v1/lending/overview?today=2026-10-15')->assertOk()->assertJsonPath('data.attention_counts.due_soon', 2)->assertJsonPath('data.attention_counts.overdue', 1);
    $this->getJson('/api/v1/lending/attention?kind=due_soon&today=2026-10-15')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.due_on', '2026-10-15')->assertJsonPath('data.1.due_on', '2026-10-22');
});
