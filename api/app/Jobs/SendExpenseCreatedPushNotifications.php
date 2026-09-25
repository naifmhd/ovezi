<?php

namespace App\Jobs;

use App\Models\Expense;
use App\NotificationType;
use App\Services\ExpoPushService;
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
    public function handle(ExpoPushService $pushService): void
    {
        $expense = Expense::query()->with([
            'creator:id,name',
            'group:id,name',
            'payerPlaceholder:id,claimed_by',
            'splits:id,expense_id,user_id,placeholder_id',
            'splits.placeholder:id,claimed_by',
        ])->find($this->expenseId);

        if ($expense === null || $expense->expense_type->value === 'personal') {
            return;
        }

        $userIds = collect([$expense->payer_user_id, $expense->payerPlaceholder?->claimed_by])
            ->concat($expense->splits->flatMap(fn ($split): array => [
                $split->user_id,
                $split->placeholder?->claimed_by,
            ]))
            ->filter(fn (mixed $userId): bool => is_numeric($userId)
                && (int) $userId > 0
                && (int) $userId !== $expense->created_by)
            ->map(fn (mixed $userId): int => (int) $userId)
            ->unique()
            ->values()
            ->all();

        if ($userIds === []) {
            return;
        }
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
