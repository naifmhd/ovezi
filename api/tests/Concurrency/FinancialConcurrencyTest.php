<?php

use App\ExpenseType;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\ExpenseSplit;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class, DatabaseMigrations::class);

beforeEach(function () {
    if (config('database.default') !== 'mysql') {
        $this->markTestSkipped('Requires an isolated MySQL test database to exercise row locks.');
    }
});

/** @return list<array{status: int, body: array}> */
function concurrentFinancialRequests(array $requests): array
{
    $database = config('database.connections.mysql');
    $environment = ['APP_ENV' => 'testing', 'APP_KEY' => config('app.key'), 'DB_CONNECTION' => 'mysql',
        'DB_HOST' => $database['host'], 'DB_PORT' => (string) $database['port'], 'DB_DATABASE' => $database['database'],
        'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => $database['password'], 'DB_URL' => '',
        'QUEUE_CONNECTION' => 'sync', 'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'SESSION_DRIVER' => 'array', 'BROADCAST_CONNECTION' => 'null'];
    $startAt = microtime(true) + 0.5;
    $processes = array_map(function (array $request) use ($environment, $startAt): Process {
        $process = new Process([PHP_BINARY, base_path('tests/Fixtures/financial-request.php')], base_path(), $environment);
        $process->setInput(json_encode([...$request, 'start_at' => $startAt], JSON_THROW_ON_ERROR));
        $process->setTimeout(20);
        $process->start();

        return $process;
    }, $requests);

    return array_map(function (Process $process): array {
        $process->wait();
        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

        return json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
    }, $processes);
}

it('commits one financial submission when identical requests arrive concurrently', function () {
    Currency::factory()->mvr()->create();
    $user = User::factory()->create(['default_currency_code' => 'MVR']);
    $request = ['token' => $user->createToken('phone')->plainTextToken, 'key' => (string) Str::uuid(), 'path' => '/api/v1/expenses',
        'body' => ['expense_type' => 'personal', 'payer_user_id' => $user->id, 'description' => 'Lunch', 'amount_minor' => 1200, 'currency_code' => 'MVR', 'occurred_at' => now()->toISOString()]];
    $results = concurrentFinancialRequests([$request, $request]);
    expect(array_column($results, 'status'))->toBe([201, 201]);
    expect($results[0]['body'])->toBe($results[1]['body']);
    $this->assertDatabaseCount('expenses', 1);
    $this->assertDatabaseCount('financial_submissions', 1);
    $this->assertDatabaseCount('activity_logs', 1);
});

it('prevents concurrent payments from exceeding the same balance', function (bool $isGroup) {
    Currency::factory()->mvr()->create();
    [$creditor, $debtor] = User::factory()->count(2)->create(['default_currency_code' => 'MVR'])->all();
    $group = $isGroup ? Group::factory()->for($creditor, 'creator')->create(['reporting_currency_code' => 'MVR']) : null;
    if ($group) {
        GroupMember::factory()->owner()->for($group)->for($creditor)->create();
        GroupMember::factory()->for($group)->for($debtor)->create();
    } else {
        Friendship::factory()->accepted()->create(['user_id' => $creditor->id, 'friend_id' => $debtor->id, 'requested_by' => $creditor->id]);
    }
    $expense = Expense::factory()->create(['expense_type' => $isGroup ? ExpenseType::Group : ExpenseType::Direct,
        'group_id' => $group?->id, 'payer_user_id' => $creditor->id, 'created_by' => $creditor->id,
        'amount_minor' => 1000, 'reporting_amount_minor' => 1000, 'currency_code' => 'MVR', 'reporting_currency_code' => 'MVR']);
    foreach ([$creditor, $debtor] as $user) {
        ExpenseSplit::factory()->for($expense)->for($user)->create(['amount_owed_minor' => 500, 'reporting_amount_owed_minor' => 500]);
    }
    $body = ['from_user_id' => $debtor->id, 'to_user_id' => $creditor->id, 'amount_minor' => 300,
        'currency_code' => 'MVR', 'reporting_currency_code' => 'MVR', 'occurred_at' => now()->toISOString()];
    $path = $group ? "/api/v1/groups/{$group->id}/settlements" : '/api/v1/settlements';
    $requests = array_map(fn (User $actor): array => ['token' => $actor->createToken('phone')->plainTextToken,
        'key' => (string) Str::uuid(), 'path' => $path, 'body' => $body], [$creditor, $debtor]);
    $statuses = array_column(concurrentFinancialRequests($requests), 'status');
    sort($statuses);
    expect($statuses)->toBe([201, 422]);
    $this->assertDatabaseCount('settlements', 1);
})->with(['group' => true, 'direct' => false]);
