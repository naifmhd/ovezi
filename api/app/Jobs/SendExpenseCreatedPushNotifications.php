<?php

namespace App\Jobs;

use App\Models\Expense;
use App\NotificationType;
use App\Services\ExpoPushService;
use App\Services\RealtimeAudience;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendExpenseCreatedPushNotifications implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $expenseId) {}

    /**
     * Execute the job.
     */
    public function handle(ExpoPushService $pushService, RealtimeAudience $audience): void
    {
        $expense = Expense::query()->with(['creator:id,name', 'group:id,name'])->find($this->expenseId);

        if ($expense === null || $expense->expense_type->value === 'personal') {
            return;
        }

        $userIds = array_values(array_filter(
            $audience->for('expense', $expense->id),
            fn (int $userId): bool => $userId !== $expense->created_by,
        ));
        $body = $expense->group === null
            ? $expense->description
            : "{$expense->description} in {$expense->group->name}";

        $pushService->sendToUsers(
            $userIds,
            NotificationType::ExpenseCreated,
            $expense->group_id,
            "{$expense->creator->name} added an expense",
            $body,
            [
                'type' => NotificationType::ExpenseCreated->value,
                'expense_id' => $expense->id,
                'group_id' => $expense->group_id,
                'path' => "/(app)/expenses/{$expense->id}",
            ],
        );
    }
}
