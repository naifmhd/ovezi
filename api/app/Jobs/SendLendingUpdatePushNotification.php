<?php

namespace App\Jobs;

use App\Models\LendingLoan;
use App\NotificationType;
use App\Services\ExpoPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendLendingUpdatePushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public array $backoff = [10, 60, 300];

    public function __construct(public int $loanId, public int $recipientId) {}

    public function handle(ExpoPushService $push): void
    {
        $loan = LendingLoan::query()->with('contact')->find($this->loanId);
        if (! $loan || $loan->shared_with === null || ! in_array($this->recipientId, [$loan->shared_with, $loan->contact->owner_id], true)) {
            return;
        }
        $push->sendToUsers([$this->recipientId], NotificationType::PaymentReceived, null, 'Lending update', 'A shared loan or repayment was updated. Open Ovezi to review it.', ['type' => 'lending', 'path' => '/(app)/lending/loan?id='.$loan->id]);
    }
}
