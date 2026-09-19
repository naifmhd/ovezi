<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteExpenseReceipt
{
    public function execute(User $actor, Expense $expense): void
    {
        $path = $expense->receipt_image_path;

        if ($path === null) {
            return;
        }

        DB::transaction(function () use ($actor, $expense): void {
            $expense->update(['receipt_image_path' => null]);

            ActivityLog::query()->create([
                'group_id' => $expense->group_id,
                'actor_id' => $actor->id,
                'subject_type' => $expense->getMorphClass(),
                'subject_id' => $expense->id,
                'event' => 'expense.updated',
                'metadata' => ['receipt' => 'removed'],
            ]);
        });

        Storage::disk((string) config('filesystems.receipts_disk'))->delete($path);
    }
}
