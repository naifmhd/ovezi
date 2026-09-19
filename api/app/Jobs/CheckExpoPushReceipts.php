<?php

namespace App\Jobs;

use App\Services\ExpoPushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CheckExpoPushReceipts implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    /** @param array<string, int> $receiptTokenIds */
    public function __construct(public readonly array $receiptTokenIds) {}

    /**
     * Execute the job.
     */
    public function handle(ExpoPushService $pushService): void
    {
        $pushService->checkReceipts($this->receiptTokenIds);
    }
}
