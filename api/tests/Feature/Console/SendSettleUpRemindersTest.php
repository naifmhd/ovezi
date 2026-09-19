<?php

use App\Jobs\SendGroupSettleUpReminders;
use App\Models\Currency;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('queues a reminder check for every group', function () {
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $firstGroup = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
    ]);
    $secondGroup = Group::factory()->for($owner, 'creator')->create([
        'reporting_currency_code' => $currency->code,
        'archived_at' => now(),
    ]);
    Queue::fake([SendGroupSettleUpReminders::class]);

    $this->artisan('notifications:send-settle-up-reminders')
        ->expectsOutput('Queued settle-up reminder checks for 2 groups.')
        ->assertSuccessful();

    Queue::assertPushed(SendGroupSettleUpReminders::class, 2);
    Queue::assertPushed(
        SendGroupSettleUpReminders::class,
        fn (SendGroupSettleUpReminders $job): bool => $job->groupId === $firstGroup->id,
    );
    Queue::assertPushed(
        SendGroupSettleUpReminders::class,
        fn (SendGroupSettleUpReminders $job): bool => $job->groupId === $secondGroup->id,
    );
});
