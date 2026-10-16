<?php

use App\Actions\DeleteAccount;
use App\Events\DomainChanged;
use App\Models\Currency;
use App\Models\LendingContact;
use App\Models\LendingLoan;
use App\Models\User;
use App\Notifications\LendingInvitation;
use App\Notifications\LendingUpdate;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

beforeEach(function () {
    Notification::fake([LendingInvitation::class, LendingUpdate::class]);
});

function lendingFixture(): array
{
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $person = LendingContact::factory()->create(['owner_id' => $owner->id]);

    return [$owner, $person];
}

function loanPayload(array $overrides = []): array
{
    return [...['direction' => 'lent', 'amount_minor' => 10000, 'currency_code' => 'MVR', 'occurred_on' => now()->toDateString()], ...$overrides];
}

it('requires authentication for lending', function () {
    $this->getJson('/api/v1/lending/contacts')->assertUnauthorized();
});

it('creates a name-only person and keeps currencies and directions separate', function () {
    [$owner, $person] = lendingFixture();
    Currency::factory()->create(['code' => 'USD']);
    $this->actingAs($owner)->postJson('/api/v1/lending/contacts', ['name' => 'Ahmed'])->assertCreated()->assertJsonPath('data.email', null);
    foreach ([['MVR', 'lent', 10000], ['USD', 'lent', 2000], ['MVR', 'borrowed', 500]] as [$currency, $direction, $amount]) {
        $this->postJson("/api/v1/lending/contacts/{$person->id}/loans", loanPayload(['currency_code' => $currency, 'direction' => $direction, 'amount_minor' => $amount]))->assertCreated();
    }
    $this->postJson("/api/v1/lending/contacts/{$person->id}/repayments", loanPayload(['amount_minor' => 3000]))->assertCreated();
    $response = $this->getJson("/api/v1/lending/contacts/{$person->id}")->assertOk()->assertJsonCount(3, 'data.balances');
    expect(collect($response->json('data.balances'))->where('currency_code', 'MVR')->where('direction', 'lent')->first()['outstanding_minor'])->toBe(7000);
    expect(collect($response->json('data.balances'))->where('currency_code', 'USD')->first()['outstanding_minor'])->toBe(2000);
    Notification::assertNothingSent();
});

it('allocates oldest first and rejects currency overpayments without partial writes', function () {
    [$owner, $person] = lendingFixture();
    $old = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'amount_minor' => 100, 'occurred_on' => now()->subDay()]);
    $new = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'amount_minor' => 200]);
    $this->actingAs($owner)->postJson("/api/v1/lending/contacts/{$person->id}/repayments", loanPayload(['amount_minor' => 150]))->assertCreated()->assertJsonCount(2, 'data');
    expect($old->fresh()->repaid_minor)->toBe(100);
    expect($new->fresh()->repaid_minor)->toBe(50);
    $this->postJson("/api/v1/lending/contacts/{$person->id}/repayments", loanPayload(['amount_minor' => 151]))->assertUnprocessable()->assertJsonValidationErrors('amount_minor');
    $this->assertDatabaseCount('lending_repayments', 2);
});

it('prevents strangers and unverified email matches from accessing private records', function () {
    [$owner, $person] = lendingFixture();
    $stranger = User::factory()->unverified()->create();
    $person->update(['email' => $stranger->email, 'email_hash' => hash('sha256', mb_strtolower($stranger->email))]);
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'private_note' => 'Only me']);
    $this->actingAs($stranger)->getJson("/api/v1/lending/contacts/{$person->id}")->assertNotFound();
    $this->getJson("/api/v1/lending/loans/{$loan->id}")->assertNotFound();
    $this->getJson('/api/v1/lending/invitations')->assertJsonCount(0, 'data');
    $this->postJson("/api/v1/lending/contacts/{$person->id}/repayments", loanPayload())->assertNotFound();
});

it('shares selected loans only after verified acceptance and hides private notes', function () {
    [$owner, $person] = lendingFixture();
    $borrower = User::factory()->create();
    $person->update(['email' => $borrower->email, 'email_hash' => hash('sha256', mb_strtolower($borrower->email))]);
    $shared = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'private_note' => 'Only me']);
    $private = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR']);
    $this->actingAs($owner)->postJson("/api/v1/lending/contacts/{$person->id}/share", ['loan_ids' => [$shared->id]])->assertOk();
    Notification::assertSentOnDemand(LendingInvitation::class);
    $this->actingAs($borrower)->getJson("/api/v1/lending/loans/{$shared->id}")->assertNotFound();
    $this->getJson('/api/v1/lending/invitations')->assertJsonPath('data.0.loan_count', 1);
    $this->postJson("/api/v1/lending/invitations/{$person->id}/actions", ['action' => 'accept'])->assertOk();
    $this->getJson("/api/v1/lending/loans/{$shared->id}")->assertOk()->assertJsonPath('data.direction', 'borrowed')->assertJsonPath('data.private_note', null)->assertJsonPath('data.acknowledgement', 'pending');
    $this->getJson("/api/v1/lending/loans/{$private->id}")->assertNotFound();
    $this->postJson("/api/v1/lending/loans/{$shared->id}/actions", ['action' => 'acknowledge'])->assertOk()->assertJsonPath('data.acknowledgement', 'acknowledged');
    $this->getJson('/api/v1/lending/contacts')->assertJsonPath('data.0.name', $owner->name);
    $this->actingAs($owner)->patchJson("/api/v1/lending/contacts/{$person->id}", ['email' => 'different@example.com'])->assertUnprocessable();
    Notification::assertSentTo($owner, LendingUpdate::class);
});

it('reserves borrower reports until the lender confirms and preserves reversals', function () {
    [$owner, $person] = lendingFixture();
    $borrower = User::factory()->create();
    $person->update(['connected_user_id' => $borrower->id]);
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'amount_minor' => 1000, 'shared_at' => now(), 'shared_with' => $borrower->id, 'acknowledgement' => 'acknowledged']);
    $response = $this->actingAs($borrower)->postJson("/api/v1/lending/contacts/{$person->id}/repayments", loanPayload(['direction' => 'borrowed', 'amount_minor' => 600]))->assertCreated()->assertJsonPath('data.0.status', 'pending');
    $id = $response->json('data.0.id');
    expect($loan->fresh()->repaid_minor)->toBe(0);
    $this->getJson("/api/v1/lending/loans/{$loan->id}")->assertJsonPath('data.pending_minor', 600);
    $this->postJson("/api/v1/lending/contacts/{$person->id}/repayments", loanPayload(['direction' => 'borrowed', 'amount_minor' => 401]))->assertUnprocessable();
    $this->postJson("/api/v1/lending/repayments/{$id}/actions", ['action' => 'confirm'])->assertForbidden();
    $this->actingAs($owner)->postJson("/api/v1/lending/repayments/{$id}/actions", ['action' => 'confirm'])->assertOk();
    expect($loan->fresh()->repaid_minor)->toBe(600);
    $this->postJson("/api/v1/lending/repayments/{$id}/actions", ['action' => 'confirm'])->assertUnprocessable();
    $this->postJson("/api/v1/lending/repayments/{$id}/actions", ['action' => 'reverse'])->assertOk();
    expect($loan->fresh()->repaid_minor)->toBe(0);
    $this->getJson("/api/v1/lending/loans/{$loan->id}/history")->assertOk()->assertJsonPath('data.0.event', 'repayment.reverse');
    Notification::assertSentTo($owner, LendingUpdate::class);
    Notification::assertSentTo($borrower, LendingUpdate::class);
});

it('replays financial requests without duplicating loans or repayments', function () {
    [$owner, $person] = lendingFixture();
    $headers = ['Idempotency-Key' => Str::uuid()->toString()];
    $this->actingAs($owner)->postJson("/api/v1/lending/contacts/{$person->id}/loans", loanPayload(), $headers)->assertCreated();
    $this->postJson("/api/v1/lending/contacts/{$person->id}/loans", loanPayload(), $headers)->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
    $this->postJson("/api/v1/lending/contacts/{$person->id}/loans", loanPayload(['amount_minor' => 2]), $headers)->assertConflict();
    $headers = ['Idempotency-Key' => Str::uuid()->toString()];
    $this->postJson("/api/v1/lending/contacts/{$person->id}/repayments", loanPayload(), $headers)->assertCreated();
    $this->postJson("/api/v1/lending/contacts/{$person->id}/repayments", loanPayload(), $headers)->assertCreated()->assertHeader('Idempotency-Replayed', 'true');
    $this->assertDatabaseCount('lending_loans', 1);
    $this->assertDatabaseCount('lending_repayments', 1);
});

it('keeps shared history after disconnect and requires agreement to void shared loans', function () {
    [$owner, $person] = lendingFixture();
    $borrower = User::factory()->create();
    $person->update(['connected_user_id' => $borrower->id]);
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'shared_at' => now(), 'shared_with' => $borrower->id]);
    $this->actingAs($owner)->postJson("/api/v1/lending/loans/{$loan->id}/actions", ['action' => 'void'])->assertOk()->assertJsonPath('data.voided_at', null);
    $this->actingAs($borrower)->postJson("/api/v1/lending/loans/{$loan->id}/actions", ['action' => 'confirm_void'])->assertOk();
    expect($loan->fresh()->voided_at)->not->toBeNull();
    $this->postJson("/api/v1/lending/contacts/{$person->id}/disconnect")->assertOk();
    $this->getJson("/api/v1/lending/loans/{$loan->id}")->assertOk();
    $this->postJson("/api/v1/lending/contacts/{$person->id}/loans", loanPayload(['shared' => true]))->assertForbidden();
    Notification::assertSentTo($owner, LendingUpdate::class);
});

it('rejects invalid loan amounts and currencies without creating records', function (array $invalid, string $field) {
    [$owner, $person] = lendingFixture();
    $this->actingAs($owner)->postJson("/api/v1/lending/contacts/{$person->id}/loans", loanPayload($invalid))->assertUnprocessable()->assertJsonValidationErrors($field);
    $this->assertDatabaseCount('lending_loans', 0);
})->with([
    'zero' => [['amount_minor' => 0], 'amount_minor'],
    'negative' => [['amount_minor' => -10], 'amount_minor'],
    'fractional minor units' => [['amount_minor' => 1.5], 'amount_minor'],
    'unsafe amount' => [['amount_minor' => 1000000000000], 'amount_minor'],
    'unknown currency' => [['currency_code' => 'XYZ'], 'currency_code'],
    'future loan' => [['occurred_on' => '2099-01-01'], 'occurred_on'],
    'due before loan' => [['due_on' => '2000-01-01'], 'due_on'],
    'invalid direction' => [['direction' => 'other'], 'direction'],
]);

it('accepts zero and three decimal currencies as exact minor units', function () {
    [$owner, $person] = lendingFixture();
    Currency::factory()->create(['code' => 'JPY', 'minor_unit_factor' => 1]);
    Currency::factory()->create(['code' => 'KWD', 'minor_unit_factor' => 1000]);
    foreach (['JPY', 'KWD'] as $code) {
        $this->actingAs($owner)->postJson("/api/v1/lending/contacts/{$person->id}/loans", loanPayload(['amount_minor' => 12345, 'currency_code' => $code]))->assertCreated()->assertJsonPath('data.amount_minor', 12345);
        $this->postJson("/api/v1/lending/contacts/{$person->id}/repayments", loanPayload(['amount_minor' => 45, 'currency_code' => $code]))->assertCreated();
    }
    expect(LendingLoan::query()->where('currency_code', 'JPY')->first()->repaid_minor)->toBe(45);
    expect(LendingLoan::query()->where('currency_code', 'KWD')->first()->repaid_minor)->toBe(45);
});

it('applies person filters before pagination without exposing private balances to connected users', function () {
    [$owner, $person] = lendingFixture();
    $borrower = User::factory()->create();
    $person->update(['connected_user_id' => $borrower->id]);
    LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'amount_minor' => 100, 'repaid_minor' => 100, 'shared_with' => $borrower->id, 'shared_at' => now()]);
    LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'amount_minor' => 99999]);
    $this->actingAs($borrower)->getJson('/api/v1/lending/contacts?status=settled&direction=borrowed')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.balances.0.amount_minor', 100);
    $this->getJson('/api/v1/lending/contacts?status=outstanding&direction=borrowed')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/lending/contacts?status=settled&direction=lent')->assertJsonCount(0, 'data');
    LendingContact::factory()->count(31)->create(['owner_id' => $owner->id]);
    $this->actingAs($owner)->getJson('/api/v1/lending/contacts')->assertJsonCount(30, 'data')->assertJsonPath('meta.last_page', 2);
});

it('rejects wrong or unverified invitations and expires revoked or declined selections', function () {
    [$owner, $person] = lendingFixture();
    $borrower = User::factory()->unverified()->create();
    $stranger = User::factory()->create();
    $person->update(['email' => $borrower->email, 'email_hash' => hash('sha256', mb_strtolower($borrower->email))]);
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR']);
    $this->actingAs($owner)->postJson("/api/v1/lending/contacts/{$person->id}/share", ['loan_ids' => [$loan->id]])->assertOk();
    $this->actingAs($stranger)->postJson("/api/v1/lending/invitations/{$person->id}/actions", ['action' => 'accept'])->assertNotFound();
    $this->actingAs($borrower)->postJson("/api/v1/lending/invitations/{$person->id}/actions", ['action' => 'accept'])->assertNotFound();
    $borrower->forceFill(['email_verified_at' => now()])->save();
    $person->update(['invitation_expires_at' => now()->subMinute()]);
    $this->postJson("/api/v1/lending/invitations/{$person->id}/actions", ['action' => 'accept'])->assertUnprocessable();
    $this->actingAs($owner)->postJson("/api/v1/lending/invitations/{$person->id}/actions", ['action' => 'resend'])->assertOk();
    $this->actingAs($borrower)->postJson("/api/v1/lending/invitations/{$person->id}/actions", ['action' => 'decline'])->assertOk();
    expect($loan->fresh()->shared_at)->toBeNull();
    $this->getJson("/api/v1/lending/loans/{$loan->id}")->assertNotFound();
    Notification::assertSentOnDemand(LendingInvitation::class);
});

it('revokes outstanding sharing selections when an optional email is changed', function () {
    [$owner, $person] = lendingFixture();
    $person->update(['email' => 'old@example.test', 'email_hash' => hash('sha256', 'old@example.test'), 'invitation_status' => 'pending', 'invitation_expires_at' => now()->addDay()]);
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'shared_at' => now()]);
    $this->actingAs($owner)->patchJson("/api/v1/lending/contacts/{$person->id}", ['email' => 'new@example.test'])->assertOk()->assertJsonPath('data.invitation_status', null);
    expect($loan->fresh()->shared_at)->toBeNull();
    expect($person->fresh()->email_hash)->toBe(hash('sha256', 'new@example.test'));
    Notification::assertNothingSent();
});

it('supports repayment of a selected loan and rejects a mismatched currency or date', function () {
    [$owner, $person] = lendingFixture();
    Currency::factory()->create(['code' => 'USD']);
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'USD']);
    $this->actingAs($owner)->postJson("/api/v1/lending/contacts/{$person->id}/repayments", loanPayload(['loan_id' => $loan->id]))->assertUnprocessable();
    $this->postJson("/api/v1/lending/contacts/{$person->id}/repayments", loanPayload(['loan_id' => $loan->id, 'currency_code' => 'USD', 'occurred_on' => now()->subDay()->toDateString()]))->assertUnprocessable()->assertJsonValidationErrors('occurred_on');
    $this->postJson("/api/v1/lending/contacts/{$person->id}/repayments", loanPayload(['loan_id' => $loan->id, 'currency_code' => 'USD']))->assertCreated();
    expect($loan->fresh()->repaid_minor)->toBe(10000);
});

it('keeps private allocations private when a currency repayment spans private and shared borrowed loans', function () {
    [$owner, $person] = lendingFixture();
    $lender = User::factory()->create();
    $person->update(['connected_user_id' => $lender->id]);
    $private = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'direction' => 'borrowed', 'amount_minor' => 100]);
    $shared = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'direction' => 'borrowed', 'amount_minor' => 100, 'shared_with' => $lender->id, 'shared_at' => now()]);
    $response = $this->actingAs($owner)->postJson("/api/v1/lending/contacts/{$person->id}/repayments", loanPayload(['direction' => 'borrowed', 'amount_minor' => 200]))->assertCreated();
    expect($private->fresh()->repaid_minor)->toBe(100);
    expect($shared->fresh()->repaid_minor)->toBe(0);
    $privatePayment = $response->json('data.0.id');
    $this->actingAs($lender)->postJson("/api/v1/lending/repayments/{$privatePayment}/actions", ['action' => 'reverse'])->assertNotFound();
    $this->getJson("/api/v1/lending/loans/{$shared->id}/history")->assertJsonPath('data.0.repayment.amount_minor', 100);
    Notification::assertSentTo($lender, LendingUpdate::class);
});

it('exports only visible lending history and removes private records on account deletion', function () {
    [$owner, $person] = lendingFixture();
    $borrower = User::factory()->create();
    $person->update(['connected_user_id' => $borrower->id]);
    $private = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'private_note' => 'secret']);
    $shared = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'private_note' => 'secret', 'shared_with' => $borrower->id, 'shared_at' => now()]);
    $this->actingAs($borrower)->getJson('/api/v1/me/export')->assertOk()->assertJsonCount(1, 'lending')->assertJsonPath('lending.0.private_note', null);
    app(DeleteAccount::class)->deleteVerified($owner);
    $this->assertDatabaseMissing('lending_loans', ['id' => $private->id]);
    expect($shared->fresh()->private_note)->toBeNull();
    $this->getJson("/api/v1/lending/loans/{$shared->id}")->assertOk();
    $this->getJson("/api/v1/lending/contacts/{$person->id}")->assertJsonPath('data.name', 'Deleted member')->assertJsonPath('data.is_connected', false);
});

it('sends realtime changes only to the owner until a loan is explicitly shared', function () {
    [$owner, $person] = lendingFixture();
    $borrower = User::factory()->create();
    $person->update(['connected_user_id' => $borrower->id]);
    Event::fake([DomainChanged::class]);
    $this->actingAs($owner)->postJson("/api/v1/lending/contacts/{$person->id}/loans", loanPayload())->assertCreated();
    Event::assertDispatched(DomainChanged::class, fn ($event) => $event->resource === 'lending' && $event->audience === [$owner->id]);
    Event::assertNotDispatched(DomainChanged::class, fn ($event) => in_array($borrower->id, $event->audience ?? [], true));
    Notification::assertNothingSent();
});

it('lets a connected person record a borrowed loan and the lender acknowledge it', function () {
    [$owner, $person] = lendingFixture();
    $borrower = User::factory()->create();
    $person->update(['connected_user_id' => $borrower->id]);
    LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'shared_at' => now(), 'shared_with' => $borrower->id]);
    $response = $this->actingAs($borrower)->postJson("/api/v1/lending/contacts/{$person->id}/loans", loanPayload(['direction' => 'borrowed', 'shared' => true, 'private_note' => 'Borrower note']))->assertCreated()->assertJsonPath('data.direction', 'borrowed');
    $id = $response->json('data.id');
    $this->actingAs($owner)->getJson("/api/v1/lending/loans/{$id}")->assertJsonPath('data.direction', 'lent')->assertJsonPath('data.private_note', null);
    $this->postJson("/api/v1/lending/loans/{$id}/actions", ['action' => 'acknowledge'])->assertOk();
    Notification::assertSentTo($owner, LendingUpdate::class);
});

it('preserves a pending invitation when only the name changes', function () {
    [$owner, $person] = lendingFixture();
    $person->update(['email' => 'person@example.test', 'email_hash' => hash('sha256', 'person@example.test'), 'invitation_status' => 'pending', 'invitation_expires_at' => now()->addDay()]);
    $this->actingAs($owner)->patchJson("/api/v1/lending/contacts/{$person->id}", ['name' => 'Updated name', 'email' => 'person@example.test'])->assertOk()->assertJsonPath('data.invitation_status', 'pending');
});

it('can roll out private tracking before new sharing without hiding existing shared history', function () {
    [$owner, $person] = lendingFixture();
    config(['ovezi.lending_sharing_enabled' => false]);
    $borrower = User::factory()->create();
    $person->update(['connected_user_id' => $borrower->id]);
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $person->id, 'currency_code' => 'MVR', 'shared_with' => $borrower->id, 'shared_at' => now()]);
    $this->actingAs($owner)->getJson('/api/v1/me')->assertJsonPath('data.features.lending_sharing', false);
    $this->postJson("/api/v1/lending/contacts/{$person->id}/loans", loanPayload())->assertCreated();
    $this->postJson("/api/v1/lending/contacts/{$person->id}/loans", loanPayload(['shared' => true]))->assertUnprocessable();
    $this->postJson("/api/v1/lending/contacts/{$person->id}/share", ['loan_ids' => [$loan->id]])->assertUnprocessable();
    $this->actingAs($borrower)->getJson("/api/v1/lending/loans/{$loan->id}")->assertOk();
    Notification::assertNothingSent();
});
