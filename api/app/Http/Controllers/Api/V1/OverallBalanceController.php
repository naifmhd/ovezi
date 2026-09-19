<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\DirectBalanceCalculator;
use App\Services\GroupBalanceCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OverallBalanceController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        Request $request,
        DirectBalanceCalculator $directBalanceCalculator,
        GroupBalanceCalculator $groupBalanceCalculator,
    ): JsonResponse {
        $user = $request->user();
        $userKey = "user:{$user->id}";
        $groups = Group::query()
            ->whereHas('activeMembers', fn (Builder $query): Builder => $query->whereBelongsTo($user))
            ->select(['id', 'name', 'reporting_currency_code', 'archived_at'])
            ->orderBy('name')
            ->get()
            ->map(function (Group $group) use ($groupBalanceCalculator, $userKey): array {
                $balances = $groupBalanceCalculator->calculate($group);

                return [
                    'group_id' => $group->id,
                    'name' => $group->name,
                    'currency_code' => $group->reporting_currency_code,
                    'balance_minor' => $balances[$userKey] ?? 0,
                    'archived_at' => $group->archived_at,
                ];
            })
            ->values();
        $direct = collect($directBalanceCalculator->calculateFor($user));
        $totalsByCurrency = $groups
            ->map(fn (array $group): array => [
                'currency_code' => $group['currency_code'],
                'balance_minor' => $group['balance_minor'],
            ])
            ->concat($direct->map(fn (array $balance): array => [
                'currency_code' => $balance['currency_code'],
                'balance_minor' => $balance['balance_minor'],
            ]))
            ->groupBy('currency_code')
            ->map(fn ($balances, string $currencyCode): array => [
                'currency_code' => $currencyCode,
                'balance_minor' => $balances->sum('balance_minor'),
            ])
            ->sortBy('currency_code')
            ->values();

        return response()->json(['data' => [
            'groups' => $groups,
            'direct' => $direct,
            'totals_by_currency' => $totalsByCurrency,
        ]]);
    }
}
