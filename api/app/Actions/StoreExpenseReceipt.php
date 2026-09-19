<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class StoreExpenseReceipt
{
    public function execute(User $actor, Expense $expense, UploadedFile $receipt): Expense
    {
        $diskName = (string) config('filesystems.receipts_disk');
        $newPath = $receipt->store("receipts/{$expense->id}", $diskName);

        if (! is_string($newPath)) {
            throw new RuntimeException('The receipt image could not be stored.');
        }

        $oldPath = $expense->receipt_image_path;

        try {
            DB::transaction(function () use ($actor, $expense, $newPath): void {
                $expense->update(['receipt_image_path' => $newPath]);

                ActivityLog::query()->create([
                    'group_id' => $expense->group_id,
                    'actor_id' => $actor->id,
                    'subject_type' => $expense->getMorphClass(),
                    'subject_id' => $expense->id,
                    'event' => 'expense.updated',
                    'metadata' => ['receipt' => 'uploaded'],
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk($diskName)->delete($newPath);

            throw $exception;
        }

        if ($oldPath !== null && $oldPath !== $newPath) {
            Storage::disk($diskName)->delete($oldPath);
        }

        return $expense->refresh();
    }
}
