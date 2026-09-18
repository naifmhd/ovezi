<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\DebtSimplifier;
use App\Services\GroupBalanceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class GroupBalanceController extends Controller
{
    public function __invoke(
        Group $group,
        GroupBalanceCalculator $balanceCalculator,
        DebtSimplifier $debtSimplifier,
    ): JsonResponse {
        Gate::authorize('view', $group);

        $balances = $balanceCalculator->calculate($group);
        $group->load([
            'activeMembers.user:id,name',
            'activeMembers.placeholder:id,name',
        ]);

        $members = $group->activeMembers->map(function ($member) use ($balances, $balanceCalculator): array {
            $key = $balanceCalculator->participantKey($member->user_id, $member->placeholder_id);

            return [
                'member_id' => $member->id,
                'participant' => [
                    'key' => $key,
                    'user_id' => $member->user_id,
                    'placeholder_id' => $member->placeholder_id,
                    'name' => $member->user?->name ?? $member->placeholder?->name,
                ],
                'balance_minor' => $balances[$key] ?? 0,
            ];
        })->values();

        return response()->json(['data' => [
            'group_id' => $group->id,
            'currency_code' => $group->reporting_currency_code,
            'members' => $members,
            'suggested_settlements' => $debtSimplifier->simplify($balances),
        ]]);
    }
}
