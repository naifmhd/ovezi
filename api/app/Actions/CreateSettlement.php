<?php

namespace App\Actions;

use App\GroupMemberRole;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Group;
use App\Models\Settlement;
use App\Models\User;
use App\Services\ExchangeRateResolver;
use App\Services\GroupBalanceCalculator;
use App\Services\MoneyConverter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSettlement
{
    public function __construct(
        private readonly ExchangeRateResolver $exchangeRateResolver,
        private readonly MoneyConverter $moneyConverter,
        private readonly GroupBalanceCalculator $balanceCalculator,
    ) {}

    /**
     * @param array{
     *   from_user_id?: int|null, from_placeholder_id?: int|null,
     *   to_user_id?: int|null, to_placeholder_id?: int|null,
     *   amount_minor: int, currency_code: string, method?: string|null,
     *   note?: string|null, occurred_at: CarbonImmutable
     * } $data
     */
    public function execute(User $actor, Group $group, array $data): Settlement
    {
        $fromKey = $this->balanceCalculator->participantKey(
            $data['from_user_id'] ?? null,
            $data['from_placeholder_id'] ?? null,
        );
        $toKey = $this->balanceCalculator->participantKey(
            $data['to_user_id'] ?? null,
            $data['to_placeholder_id'] ?? null,
        );

        $activeMembers = $group->activeMembers()->get();
        $activeKeys = $activeMembers->mapWithKeys(fn ($member): array => [
            $this->balanceCalculator->participantKey($member->user_id, $member->placeholder_id) => true,
        ]);

        if (! $activeKeys->has($fromKey) || ! $activeKeys->has($toKey)) {
            throw ValidationException::withMessages([
                'settlement' => 'Both settlement participants must be active group members.',
            ]);
        }

        $actorKey = "user:{$actor->id}";
        $isOwner = $activeMembers->contains(fn ($member): bool => $member->user_id === $actor->id
            && $member->role === GroupMemberRole::Owner);

        if (! $isOwner && $actorKey !== $fromKey && $actorKey !== $toKey) {
            throw ValidationException::withMessages([
                'settlement' => 'You must be part of the settlement you record.',
            ]);
        }

        $baseCurrency = Currency::query()->findOrFail($data['currency_code']);
        $reportingCurrency = Currency::query()->findOrFail($group->reporting_currency_code);
        $resolvedRate = $this->exchangeRateResolver->resolve(
            $baseCurrency->code,
            $reportingCurrency->code,
            $data['occurred_at'],
            $group,
        );
        $reportingAmountMinor = $resolvedRate->rate === null
            ? $data['amount_minor']
            : $this->moneyConverter->convert(
                $data['amount_minor'],
                $baseCurrency->minor_unit_factor,
                $reportingCurrency->minor_unit_factor,
                $resolvedRate->rate,
            );
        $balances = $this->balanceCalculator->calculate($group);
        $maximumSettlement = min(-($balances[$fromKey] ?? 0), $balances[$toKey] ?? 0);

        if ($maximumSettlement <= 0 || $reportingAmountMinor > $maximumSettlement) {
            throw ValidationException::withMessages([
                'amount_minor' => 'The settlement exceeds the amount currently owed between these balances.',
            ]);
        }

        return DB::transaction(function () use (
            $actor,
            $group,
            $data,
            $reportingCurrency,
            $reportingAmountMinor,
            $resolvedRate,
        ): Settlement {
            $settlement = Settlement::query()->create([
                'group_id' => $group->id,
                'from_user_id' => $data['from_user_id'] ?? null,
                'from_placeholder_id' => $data['from_placeholder_id'] ?? null,
                'to_user_id' => $data['to_user_id'] ?? null,
                'to_placeholder_id' => $data['to_placeholder_id'] ?? null,
                'amount_minor' => $data['amount_minor'],
                'currency_code' => $data['currency_code'],
                'reporting_amount_minor' => $reportingAmountMinor,
                'reporting_currency_code' => $reportingCurrency->code,
                'exchange_rate' => $resolvedRate->rate,
                'exchange_rate_source' => $resolvedRate->source,
                'exchange_rate_effective_date' => $resolvedRate->effectiveDate,
                'method' => $data['method'] ?? null,
                'note' => $data['note'] ?? null,
                'occurred_at' => $data['occurred_at'],
                'created_by' => $actor->id,
            ]);

            ActivityLog::query()->create([
                'group_id' => $group->id,
                'actor_id' => $actor->id,
                'subject_type' => $settlement->getMorphClass(),
                'subject_id' => $settlement->id,
                'event' => 'settlement.created',
                'metadata' => [
                    'reporting_amount_minor' => $reportingAmountMinor,
                    'reporting_currency_code' => $reportingCurrency->code,
                ],
            ]);

            return $settlement;
        });
    }
}
