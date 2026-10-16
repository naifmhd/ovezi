<?php

use App\Jobs\SendDueLendingReminders;
use App\Models\Currency;
use App\Models\LendingContact;
use App\Models\LendingLoan;
use App\Models\LendingRepayment;
use App\Models\NotificationPreference;
use App\Models\PushToken;
use App\Models\User;
use App\Services\ExpoPushService;
use App\Services\LendingLedger;
use App\Services\LendingStatementExporter;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 16)->setTime(4, 0));
    Currency::factory()->mvr()->create();
    $this->owner = User::factory()->create();
    $this->person = LendingContact::factory()->create(['owner_id' => $this->owner->id]);
});

it('filters overdue and due soon people by local day and direction and shows their nearest unpaid date', function () {
    $base = ['lending_contact_id' => $this->person->id, 'currency_code' => 'MVR'];
    LendingLoan::factory()->create([...$base, 'due_on' => '2026-10-15']);
    LendingLoan::factory()->create([...$base, 'due_on' => '2026-10-14', 'voided_at' => now()]);
    LendingLoan::factory()->create([...$base, 'due_on' => '2026-10-13', 'amount_minor' => 100, 'repaid_minor' => 100]);
    $soon = LendingContact::factory()->create(['owner_id' => $this->owner->id]);
    LendingLoan::factory()->create([...$base, 'lending_contact_id' => $soon->id, 'due_on' => '2026-10-23']);
    $later = LendingContact::factory()->create(['owner_id' => $this->owner->id]);
    LendingLoan::factory()->create([...$base, 'lending_contact_id' => $later->id, 'due_on' => '2026-10-24']);
    $this->actingAs($this->owner)->getJson('/api/v1/lending/contacts?status=overdue&direction=lent&today=2026-10-16')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->person->id)->assertJsonPath('data.0.next_due_on', '2026-10-15');
    $this->getJson('/api/v1/lending/contacts?status=due_soon&direction=lent&today=2026-10-16')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $soon->id);
    $this->getJson('/api/v1/lending/contacts?status=overdue&direction=borrowed&today=2026-10-16')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/lending/contacts?status=overdue&today=invalid')->assertUnprocessable();
});

it('does not expose private due dates to a connected person and flips the viewer direction', function () {
    $viewer = User::factory()->create();
    $this->person->update(['connected_user_id' => $viewer->id]);
    $base = ['lending_contact_id' => $this->person->id, 'currency_code' => 'MVR'];
    LendingLoan::factory()->create([...$base, 'due_on' => '2026-10-01']);
    LendingLoan::factory()->create([...$base, 'shared_with' => $viewer->id, 'due_on' => '2026-10-17']);
    $this->actingAs($viewer)->getJson('/api/v1/lending/contacts?status=overdue&today=2026-10-16')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/lending/contacts?status=due_soon&direction=borrowed&today=2026-10-16')->assertJsonCount(1, 'data')->assertJsonPath('data.0.next_due_on', '2026-10-17');
});

it('exports only visible allocations with date-filtered activity and separate current balances', function () {
    $viewer = User::factory()->create();
    $this->person->update(['connected_user_id' => $viewer->id, 'name' => 'PRIVATE CONTACT LABEL']);
    Currency::factory()->create(['code' => 'USD']);
    $base = ['lending_contact_id' => $this->person->id, 'currency_code' => 'MVR', 'occurred_on' => '2026-10-01'];
    $shared = LendingLoan::factory()->create([...$base, 'shared_with' => $viewer->id, 'amount_minor' => 1000, 'repaid_minor' => 100, 'private_note' => 'PRIVATE NOTE']);
    $private = LendingLoan::factory()->create([...$base, 'description' => 'PRIVATE LOAN']);
    LendingLoan::factory()->create([...$base, 'shared_with' => $viewer->id, 'currency_code' => 'USD', 'amount_minor' => 2000]);
    $payment = LendingRepayment::factory()->create(['lending_contact_id' => $this->person->id, 'currency_code' => 'MVR', 'amount_minor' => 900, 'occurred_on' => '2026-10-16', 'note' => 'PAYMENT NOTE']);
    $payment->loans()->attach([$shared->id => ['amount_minor' => 100], $private->id => ['amount_minor' => 800]]);
    $url = "/api/v1/lending/contacts/{$this->person->id}/statement?format=csv&from=2026-10-16&to=2026-10-16";
    $csv = $this->actingAs($viewer)->get($url)->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->getContent();
    expect($csv)->toContain('MVR,borrowed,10.00,1.00,9.00,1000,100,900', 'USD,borrowed,20.00,0.00,20.00,2000,0,2000', 'MVR,borrowed,1.00,100')
        ->not->toContain('PRIVATE', 'PAYMENT NOTE', 'MVR,borrowed,8.00,800');
    expect(substr_count($csv, 'Repayment allocation'))->toBe(1);
    $this->actingAs(User::factory()->create())->getJson($url)->assertNotFound();
});

it('renders PDF statements and escapes CSV formulas without changing money values', function () {
    $this->person->update(['name' => '=HYPERLINK("bad")']);
    LendingLoan::factory()->create(['lending_contact_id' => $this->person->id, 'currency_code' => 'MVR', 'description' => '+SUM(1,2)']);
    $url = "/api/v1/lending/contacts/{$this->person->id}/statement";
    $this->actingAs($this->owner)->get($url.'?format=pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertSee('%PDF-', false);
    $csv = $this->get($url.'?format=csv')->assertOk()->getContent();
    expect($csv)->toContain("'=HYPERLINK", "'+SUM");
    $this->getJson($url.'?format=csv&to=2026-10-16')->assertOk();
    $this->getJson($url.'?format=csv&from=2026-10-16&to=2026-10-15')->assertUnprocessable();
});

it('keeps nonconfirmed repayments explicitly labelled and out of current repaid totals', function () {
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $this->person->id, 'currency_code' => 'MVR']);
    foreach (['pending', 'rejected', 'reversed'] as $status) {
        $payment = LendingRepayment::factory()->create(['lending_contact_id' => $this->person->id, 'currency_code' => 'MVR', 'status' => $status]);
        $payment->loans()->attach($loan->id, ['amount_minor' => 100]);
    }
    $statement = app(LendingStatementExporter::class)->statement($this->owner, $this->person, null, null);
    expect($statement['balances'][0]['repaid_minor'])->toBe(0);
    expect($statement['rows']->where('type', 'Repayment allocation')->pluck('status')->all())->toBe(['pending', 'rejected', 'reversed']);
});

it('creates reschedules and cancels only the viewers optional reminder', function () {
    $viewer = User::factory()->create();
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $this->person->id, 'currency_code' => 'MVR', 'shared_with' => $viewer->id]);
    PushToken::factory()->for($this->owner)->create();
    PushToken::factory()->for($viewer)->create();
    $url = "/api/v1/lending/loans/{$loan->id}/reminder";
    $this->actingAs($this->owner)->putJson($url, ['remind_at' => '2026-10-17T09:00:00+05:00'])->assertOk()->assertJsonPath('data.remind_at', '2026-10-17T04:00:00+00:00');
    $this->putJson($url, ['remind_at' => '2026-10-18T09:00:00+05:00'])->assertOk();
    $this->assertDatabaseCount('lending_reminders', 1);
    $this->actingAs($viewer)->getJson($url)->assertJsonPath('data', null);
    $this->putJson($url, ['remind_at' => '2026-10-19T09:00:00+05:00'])->assertOk();
    $this->deleteJson($url)->assertOk();
    $this->assertDatabaseCount('lending_reminders', 1);
    $this->actingAs(User::factory()->create())->putJson($url, ['remind_at' => '2026-10-19T09:00:00+05:00'])->assertNotFound();
    $this->deleteJson($url)->assertNotFound();
});

it('requires notifications and a future date and refuses settled loans', function () {
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $this->person->id, 'currency_code' => 'MVR']);
    $url = "/api/v1/lending/loans/{$loan->id}/reminder";
    $this->actingAs($this->owner)->putJson($url, ['remind_at' => now()->subMinute()->toIso8601String()])->assertUnprocessable();
    $this->putJson($url, ['remind_at' => now()->addDay()->toIso8601String()])->assertUnprocessable();
    PushToken::factory()->for($this->owner)->create();
    NotificationPreference::factory()->for($this->owner)->create(['settle_up_reminders' => false]);
    $this->owner->unsetRelation('notificationPreference');
    $this->putJson($url, ['remind_at' => now()->addDay()->toIso8601String()])->assertUnprocessable();
    $this->owner->notificationPreference()->update(['settle_up_reminders' => true]);
    $this->owner->unsetRelation('notificationPreference');
    $loan->update(['repaid_minor' => $loan->amount_minor]);
    $this->putJson($url, ['remind_at' => now()->addDay()->toIso8601String()])->assertUnprocessable();
    $this->assertDatabaseCount('lending_reminders', 0);
});

it('delivers a due reminder only to its creator and processes it only once', function () {
    $viewer = User::factory()->create();
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $this->person->id, 'currency_code' => 'MVR', 'shared_with' => $viewer->id]);
    $token = PushToken::factory()->for($this->owner)->create();
    PushToken::factory()->for($viewer)->create();
    $this->actingAs($this->owner)->putJson("/api/v1/lending/loans/{$loan->id}/reminder", ['remind_at' => now()->addMinute()->toIso8601String()])->assertOk();
    $this->travel(2)->minutes();
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response(['data' => [['status' => 'ok']]])]);
    $job = new SendDueLendingReminders;
    $job->handle(app(ExpoPushService::class), app(LendingLedger::class));
    $job->handle(app(ExpoPushService::class), app(LendingLedger::class));
    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => count($request->data()) === 1 && $request->data()[0]['to'] === $token->expo_push_token && $request->data()[0]['data']['path'] === '/(app)/lending/loan?id='.$loan->id);
});

it('suppresses reminders after settlement voiding loss of access deletion or preference changes', function (string $change) {
    $viewer = User::factory()->create();
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $this->person->id, 'currency_code' => 'MVR', 'shared_with' => $viewer->id]);
    PushToken::factory()->for($viewer)->create();
    $this->actingAs($viewer)->putJson("/api/v1/lending/loans/{$loan->id}/reminder", ['remind_at' => now()->addMinute()->toIso8601String()])->assertOk();
    match ($change) {
        'settled' => $loan->update(['repaid_minor' => $loan->amount_minor]),
        'void' => $loan->update(['voided_at' => now()]),
        'access' => $loan->update(['shared_with' => null]),
        'deleted' => $viewer->delete(),
        'preference' => NotificationPreference::factory()->for($viewer)->create(['settle_up_reminders' => false]),
    };
    $this->travel(2)->minutes();
    Http::preventStrayRequests();
    Http::fake();
    (new SendDueLendingReminders)->handle(app(ExpoPushService::class), app(LendingLedger::class));
    Http::assertNothingSent();
    expect(DB::table('lending_reminders')->whereNull('sent_at')->count())->toBe(0);
})->with(['settled', 'void', 'access', 'deleted', 'preference']);

it('leaves failed deliveries pending for retry', function () {
    $loan = LendingLoan::factory()->create(['lending_contact_id' => $this->person->id, 'currency_code' => 'MVR']);
    PushToken::factory()->for($this->owner)->create();
    $this->actingAs($this->owner)->putJson("/api/v1/lending/loans/{$loan->id}/reminder", ['remind_at' => now()->addMinute()->toIso8601String()])->assertOk();
    $this->travel(2)->minutes();
    Http::fake(['*' => Http::response([], 503)]);
    expect(fn () => (new SendDueLendingReminders)->handle(app(ExpoPushService::class), app(LendingLedger::class)))->toThrow(RequestException::class);
    expect(DB::table('lending_reminders')->whereNull('sent_at')->count())->toBe(1);
});
