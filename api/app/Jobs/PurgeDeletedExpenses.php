<?php

namespace App\Jobs;

use App\Models\Expense;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PurgeDeletedExpenses implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    public function handle(): void
    {
        Expense::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(30))
            ->chunkById(100, function ($expenses): void {
                foreach ($expenses as $expense) {
                    if ($expense->receipt_image_path !== null) {
                        $disk = Storage::disk((string) config('filesystems.receipts_disk'));

                        if ($disk->exists($expense->receipt_image_path)
                            && ! $disk->delete($expense->receipt_image_path)) {
                            throw new RuntimeException('The deleted expense receipt could not be removed.');
                        }
                    }

                    $expense->forceDelete();
                }
            });
    }
}
