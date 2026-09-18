<?php

namespace App\Services;

use App\Models\Group;

class GroupBalanceCalculator
{
    /**
     * Positive values mean the participant is owed money; negative values mean they owe money.
     *
     * @return array<string, int>
     */
    public function calculate(Group $group): array
    {
        $balances = [];

        $group->expenses()
            ->with('splits:id,expense_id,user_id,placeholder_id,reporting_amount_owed_minor')
            ->select(['id', 'payer_user_id', 'payer_placeholder_id', 'reporting_amount_minor'])
            ->orderBy('id')
            ->each(function ($expense) use (&$balances): void {
                $payerKey = $this->participantKey($expense->payer_user_id, $expense->payer_placeholder_id);
                $balances[$payerKey] = ($balances[$payerKey] ?? 0) + $expense->reporting_amount_minor;

                foreach ($expense->splits as $split) {
                    $participantKey = $this->participantKey($split->user_id, $split->placeholder_id);
                    $balances[$participantKey] = ($balances[$participantKey] ?? 0)
                        - $split->reporting_amount_owed_minor;
                }
            });

        $group->settlements()
            ->select([
                'from_user_id', 'from_placeholder_id', 'to_user_id', 'to_placeholder_id',
                'reporting_amount_minor',
            ])
            ->orderBy('id')
            ->each(function ($settlement) use (&$balances): void {
                $fromKey = $this->participantKey(
                    $settlement->from_user_id,
                    $settlement->from_placeholder_id,
                );
                $toKey = $this->participantKey(
                    $settlement->to_user_id,
                    $settlement->to_placeholder_id,
                );
                $balances[$fromKey] = ($balances[$fromKey] ?? 0) + $settlement->reporting_amount_minor;
                $balances[$toKey] = ($balances[$toKey] ?? 0) - $settlement->reporting_amount_minor;
            });

        ksort($balances);

        return $balances;
    }

    public function participantKey(?int $userId, ?int $placeholderId): string
    {
        if (($userId === null) === ($placeholderId === null)) {
            throw new \InvalidArgumentException('Select exactly one user or placeholder.');
        }

        return $userId !== null ? "user:{$userId}" : "placeholder:{$placeholderId}";
    }
}
