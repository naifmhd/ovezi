<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Settlement;
use Illuminate\Support\Collection;

class RealtimeAudience
{
    /** @return list<int> */
    public function for(string $resource, int $resourceId): array
    {
        $userIds = match ($resource) {
            'expense' => $this->forExpense($resourceId),
            'settlement' => $this->forSettlement($resourceId),
            'group' => $this->forGroup($resourceId),
            'group_member' => $this->forGroupMember($resourceId),
            default => collect(),
        };

        return $userIds
            ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return Collection<int, int|null> */
    private function forExpense(int $expenseId): Collection
    {
        $expense = Expense::withTrashed()->with([
            'payerPlaceholder:id,claimed_by',
            'splits:id,expense_id,user_id,placeholder_id',
            'splits.placeholder:id,claimed_by',
        ])->find($expenseId);

        if ($expense === null) {
            return collect();
        }

        return collect([$expense->created_by, $expense->payer_user_id, $expense->payerPlaceholder?->claimed_by])
            ->concat($expense->splits->flatMap(fn ($split): array => [
                $split->user_id,
                $split->placeholder?->claimed_by,
            ]))
            ->concat($expense->group_id ? $this->activeGroupUserIds($expense->group_id) : []);
    }

    /** @return Collection<int, int|null> */
    private function forSettlement(int $settlementId): Collection
    {
        $settlement = Settlement::withTrashed()->with([
            'fromPlaceholder:id,claimed_by',
            'toPlaceholder:id,claimed_by',
        ])->find($settlementId);

        if ($settlement === null) {
            return collect();
        }

        return collect([
            $settlement->created_by,
            $settlement->from_user_id,
            $settlement->fromPlaceholder?->claimed_by,
            $settlement->to_user_id,
            $settlement->toPlaceholder?->claimed_by,
        ])->concat($settlement->group_id ? $this->activeGroupUserIds($settlement->group_id) : []);
    }

    /** @return Collection<int, int|null> */
    private function forGroup(int $groupId): Collection
    {
        $group = Group::withTrashed()->find($groupId);

        return collect([$group?->created_by])->concat($this->activeGroupUserIds($groupId));
    }

    /** @return Collection<int, int|null> */
    private function forGroupMember(int $memberId): Collection
    {
        $member = GroupMember::query()->with('placeholder:id,claimed_by')->find($memberId);

        if ($member === null) {
            return collect();
        }

        return collect([$member->user_id, $member->placeholder?->claimed_by])
            ->concat($this->activeGroupUserIds($member->group_id));
    }

    /** @return list<int> */
    private function activeGroupUserIds(int $groupId): array
    {
        return GroupMember::query()
            ->where('group_id', $groupId)
            ->whereNull('left_at')
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->all();
    }
}
