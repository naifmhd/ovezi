<?php

use App\Jobs\PurgeDeletedExpenses;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('replays a completed financial submission without repeating the write', function (string $path, array $extra) {
    Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => 'MVR']);
    $token = $user->createToken('phone')->plainTextToken;
    $key = Str::uuid()->toString();
    $input = ['expense_type' => 'personal', 'payer_user_id' => $user->id, 'description' => 'Dinner', 'amount_minor' => 124000, 'currency_code' => 'MVR', 'occurred_at' => '2026-09-25T12:00:00Z', ...$extra];
    $first = $this->withToken($token)->withHeader('Idempotency-Key', $key)->postJson($path, $input)->assertCreated();
    $this->postJson($path, $input)->assertCreated()->assertHeader('Idempotency-Replayed', 'true')->assertExactJson($first->json());
    $this->assertDatabaseCount('expenses', 1);
    $this->assertDatabaseCount('financial_submissions', 1);
    $cached = Crypt::decryptString(DB::table('financial_submissions')->value('response_body'));
    expect(data_get(json_decode($cached, true), $path === '/api/v1/expenses' ? 'data.payer.name' : 'data.expense.payer.name'))->toBeNull();
    $this->postJson($path, [...$input, 'amount_minor' => 125000])->assertConflict();
    DB::table('financial_submissions')->update(['created_at' => now()->subDays(31)]);
    (new PurgeDeletedExpenses)->handle();
    expect(DB::table('financial_submissions')->value('response_body'))->toBe('');
    $this->postJson($path, $input)->assertGone();
    $this->assertDatabaseCount('expenses', 1);
})->with([
    'expense' => ['/api/v1/expenses', []],
    'recurring expense' => ['/api/v1/recurring-expenses', ['frequency' => 'monthly']],
]);

it('does not consume submission identifiers for invalid requests', function () {
    Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => 'MVR']);
    $this->withToken($user->createToken('phone')->plainTextToken)->withHeader('Idempotency-Key', Str::uuid()->toString())
        ->postJson('/api/v1/expenses', [])->assertUnprocessable();
    $this->assertDatabaseCount('financial_submissions', 0);
});

it('isolates submission identifiers by authenticated account', function () {
    Currency::factory()->mvr()->create();
    $key = Str::uuid()->toString();
    foreach (User::factory()->count(2)->create(['default_currency_code' => 'MVR']) as $user) {
        app('auth')->forgetGuards();
        $this->withToken($user->createToken('phone')->plainTextToken)->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/expenses', ['expense_type' => 'personal', 'payer_user_id' => $user->id, 'description' => 'Lunch', 'amount_minor' => 3000, 'currency_code' => 'MVR', 'occurred_at' => '2026-09-25T12:00:00Z'])->assertCreated();
    }
    $this->assertDatabaseCount('expenses', 2);
});
