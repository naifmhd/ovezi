<?php

namespace App\Jobs;

use App\Models\User;
use App\NotificationType;
use App\Services\ExpoPushService;
use App\Services\LendingLedger;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class SendDueLendingReminders implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;
    public int $uniqueFor = 3600;
    public array $backoff = [60, 300, 900];

    public function handle(ExpoPushService $push, LendingLedger $ledger): void
    {
        DB::table('lending_reminders')->whereNull('sent_at')->where('remind_at', '<=', now())->orderBy('id')->chunkById(100, function ($reminders) use ($push, $ledger): void {
            foreach ($reminders as $candidate) {
                DB::transaction(function () use ($candidate, $push, $ledger): void {
                    // The same user lock serializes cancellation, rescheduling and delivery.
                    $user = User::query()->lockForUpdate()->find($candidate->user_id);
                    $reminder = DB::table('lending_reminders')->where('id', $candidate->id)->whereNull('sent_at')->where('remind_at', '<=', now())->lockForUpdate()->first();
                    if (! $reminder) { return; }
                    $loan = $user ? $ledger->loans($user)->whereNull('voided_at')->whereColumn('amount_minor', '>', 'repaid_minor')->find($reminder->lending_loan_id) : null;
                    if ($loan) {
                        $push->sendToUsers([$user->id], NotificationType::SettleUpReminders, null, 'Your lending reminder',
                            'You asked to review this loan. Open Ovezi to see its current balance.',
                            ['type' => 'lending', 'reminder_id' => $reminder->id, 'path' => '/(app)/lending/loan?id='.$loan->id]);
                    }
                    DB::table('lending_reminders')->where('id', $reminder->id)->update(['sent_at' => now(), 'updated_at' => now()]);
                });
            }
        });
    }
}
