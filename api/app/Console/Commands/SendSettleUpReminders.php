<?php

namespace App\Console\Commands;

use App\Jobs\SendGroupSettleUpReminders;
use App\Models\Group;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:send-settle-up-reminders')]
#[Description('Queue settle-up reminders for groups with open balances')]
class SendSettleUpReminders extends Command
{
    public function handle(): int
    {
        $queuedGroups = 0;

        Group::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($groups) use (&$queuedGroups): void {
                foreach ($groups as $group) {
                    SendGroupSettleUpReminders::dispatch($group->id);
                    $queuedGroups++;
                }
            });

        $this->info("Queued settle-up reminder checks for {$queuedGroups} groups.");

        return self::SUCCESS;
    }
}
