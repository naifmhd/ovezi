<?php

namespace App\Jobs;

use App\Models\Settlement;
use App\NotificationType;
use App\Services\ExpoPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendSettlementReceivedPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $settlementId) {}

    /**
     * Execute the job.
     */
    public function handle(ExpoPushService $pushService): void
    {
        $settlement = Settlement::query()->with([
            'fromUser:id,name',
            'fromPlaceholder:id,name',
            'toPlaceholder:id,claimed_by',
            'group:id,name',
        ])->find($this->settlementId);

        if ($settlement === null) {
            return;
        }

        $recipientId = $settlement->to_user_id ?? $settlement->toPlaceholder?->claimed_by;

        if ($recipientId === null || $recipientId === $settlement->created_by) {
            return;
        }

        $senderName = $settlement->fromUser?->name ?? $settlement->fromPlaceholder?->name ?? 'Someone';
        $body = $settlement->group === null
            ? "{$senderName} recorded a payment to you."
            : "{$senderName} recorded a settlement in {$settlement->group->name}.";
        $path = $settlement->group_id === null
            ? '/(app)/(tabs)'
            : "/(app)/groups/{$settlement->group_id}";

        $pushService->sendToUsers(
            [$recipientId],
            NotificationType::PaymentReceived,
            $settlement->group_id,
            'Payment received',
            $body,
            [
                'type' => NotificationType::PaymentReceived->value,
                'settlement_id' => $settlement->id,
                'group_id' => $settlement->group_id,
                'path' => $path,
            ],
        );
    }
}
