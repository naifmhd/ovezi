<?php

namespace App\Actions;

use App\Jobs\SendSettlementReceivedPushNotification;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Placeholder;
use App\Models\Settlement;
use App\Models\User;
use App\Services\DirectBalanceCalculator;
use App\Services\ExchangeRateResolver;
use App\Services\MoneyConverter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateDirectSettlement
{
    public function __construct(
        private readonly DirectBalanceCalculator $balanceCalculator,
        private readonly ExchangeRateResolver $exchangeRateResolver,
        private readonly MoneyConverter $moneyConverter,
    ) {}

    /**
     * @param array{
     *   from_user_id?: int|null, from_placeholder_id?: int|null,
     *   to_user_id?: int|null, to_placeholder_id?: int|null,
     *   amount_minor: int, currency_code: string, reporting_currency_code: string,
     *   method?: string|null, note?: string|null, occurred_at: CarbonImmutable
     * } $data
     */
    public function execute(User $actor, array $data): Settlement
    {
        $fromKey = $this->participantKey($data['from_user_id'] ?? null, $data['from_placeholder_id'] ?? null);
        $toKey = $this->participantKey($data['to_user_id'] ?? null, $data['to_placeholder_id'] ?? null);
        $actorKey = "user:{$actor->id}";

        if ($fromKey !== $actorKey && $toKey !== $actorKey) {
            throw ValidationException::withMessages([
                'settlement' => 'You must be part of the direct settlement you record.',
            ]);
        }

        $counterpartKey = $fromKey === $actorKey ? $toKey : $fromKey;
        $this->validateCounterpart($actor, $counterpartKey);

        $baseCurrency = Currency::query()->findOrFail($data['currency_code']);
        $reportingCurrency = Currency::query()->findOrFail($data['reporting_currency_code']);
        $resolvedRate = $this->exchangeRateResolver->resolve(
            $baseCurrency->code,
            $reportingCurrency->code,
            $data['occurred_at'],
        );
        $reportingAmountMinor = $resolvedRate->rate === null
            ? $data['amount_minor']
            : $this->moneyConverter->convert(
                $data['amount_minor'],
                $baseCurrency->minor_unit_factor,
                $reportingCurrency->minor_unit_factor,
                $resolvedRate->rate,
            );

        $balance = collect($this->balanceCalculator->calculateFor($actor))->first(
            fn (array $entry): bool => $entry['participant']['key'] === $counterpartKey
                && $entry['currency_code'] === $reportingCurrency->code,
        );
        $maximumSettlement = $fromKey === $actorKey
            ? -($balance['balance_minor'] ?? 0)
            : ($balance['balance_minor'] ?? 0);

        if ($maximumSettlement <= 0 || $reportingAmountMinor > $maximumSettlement) {
            throw ValidationException::withMessages([
                'amount_minor' => 'The settlement exceeds the amount currently owed between these balances.',
            ]);
        }

        $settlement = DB::transaction(function () use (
            $actor,
            $data,
            $reportingCurrency,
            $reportingAmountMinor,
            $resolvedRate,
        ): Settlement {
            $settlement = Settlement::query()->create([
                'group_id' => null,
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
                'group_id' => null,
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

        SendSettlementReceivedPushNotification::dispatch($settlement->id)->afterCommit();

        return $settlement;
    }

    private function validateCounterpart(User $actor, string $counterpartKey): void
    {
        [$type, $id] = explode(':', $counterpartKey, 2);

        if ($type === 'user' && ! $actor->isFriendsWith((int) $id)) {
            throw ValidationException::withMessages([
                'settlement' => 'A registered direct-settlement participant must be an accepted friend.',
            ]);
        }

        if ($type === 'placeholder' && ! Placeholder::query()
            ->whereKey((int) $id)
            ->where('created_by', $actor->id)
            ->whereNull('claimed_by')
            ->exists()) {
            throw ValidationException::withMessages([
                'settlement' => 'A placeholder must belong to you and remain unclaimed.',
            ]);
        }
    }

    private function participantKey(?int $userId, ?int $placeholderId): string
    {
        if (($userId === null) === ($placeholderId === null)) {
            throw ValidationException::withMessages([
                'settlement' => 'Select exactly one user or placeholder for each participant.',
            ]);
        }

        return $userId !== null ? "user:{$userId}" : "placeholder:{$placeholderId}";
    }
}
