<?php

namespace App\Jobs;

use App\Models\Group;
use App\NotificationType;
use App\Services\ExpoPushService;
use App\Services\GroupBalanceCalculator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendGroupSettleUpReminders implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(public readonly int $groupId) {}

    /**
     * Execute the job.
     */
    public function handle(GroupBalanceCalculator $balanceCalculator, ExpoPushService $pushService): void
    {
        $group = Group::query()->with([
            'reportingCurrency:code,minor_unit_factor',
            'activeMembers' => fn ($query) => $query->whereNotNull('user_id'),
        ])->find($this->groupId);

        if ($group === null) {
            return;
        }

        $balances = $balanceCalculator->calculate($group);

        foreach ($group->activeMembers as $membership) {
            $balanceMinor = $balances["user:{$membership->user_id}"] ?? 0;

            if ($balanceMinor >= 0) {
                continue;
            }

            $pushService->sendToUsers(
                [$membership->user_id],
                NotificationType::SettleUpReminders,
                $group->id,
                'Time to settle up',
                "You owe {$this->formatAmount(abs($balanceMinor), $group->reportingCurrency->minor_unit_factor, $group->reporting_currency_code)} in {$group->name}.",
                [
                    'type' => NotificationType::SettleUpReminders->value,
                    'group_id' => $group->id,
                    'path' => "/(app)/groups/{$group->id}",
                ],
            );
        }
    }

    public function uniqueId(): string
    {
        return (string) $this->groupId;
    }

    private function formatAmount(int $amountMinor, int $minorUnitFactor, string $currencyCode): string
    {
        $decimalPlaces = max(0, strlen((string) $minorUnitFactor) - 1);

        return sprintf(
            '%s %s',
            $currencyCode,
            number_format($amountMinor / $minorUnitFactor, $decimalPlaces, '.', ','),
        );
    }
}
