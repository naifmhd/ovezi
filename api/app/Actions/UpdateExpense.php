<?php

namespace App\Actions;

use App\Exceptions\InvalidSplit;
use App\ExpenseType;
use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\Group;
use App\Models\Placeholder;
use App\Models\User;
use App\Services\ExchangeRateResolver;
use App\Services\MoneyConverter;
use App\Services\ReportingSplitAllocator;
use App\Services\ResolvedExchangeRate;
use App\Services\SplitCalculator;
use App\SplitType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateExpense
{
    public function __construct(
        private readonly SplitCalculator $splitCalculator,
        private readonly ExchangeRateResolver $exchangeRateResolver,
        private readonly MoneyConverter $moneyConverter,
        private readonly ReportingSplitAllocator $reportingSplitAllocator,
    ) {}

    /**
     * @param array{
     *     payer_user_id?: int|null,
     *     payer_placeholder_id?: int|null,
     *     amount_minor: int,
     *     currency_code: string,
     *     description: string,
     *     category?: string|null,
     *     occurred_at: CarbonImmutable,
     *     split_type?: SplitType,
     *     participants?: list<array{user_id?: int|null, placeholder_id?: int|null, value?: int}>,
     *     expense_rate?: string|null,
     *     recalculate_rate?: bool
     * } $data
     */
    public function execute(User $editor, Expense $expense, array $data): Expense
    {
        if ($data['amount_minor'] <= 0) {
            throw ValidationException::withMessages(['amount_minor' => 'The expense amount must be greater than zero.']);
        }

        $expenseType = $expense->expense_type;
        $group = $expense->group;
        $payerUserId = $data['payer_user_id'] ?? null;
        $payerPlaceholderId = $data['payer_placeholder_id'] ?? null;
        $participants = $data['participants'] ?? [];

        $this->validateContext(
            $editor,
            $expense,
            $group,
            $payerUserId,
            $payerPlaceholderId,
            $participants,
        );

        $baseCurrency = Currency::query()->findOrFail($data['currency_code']);
        $recalculateRate = (bool) ($data['recalculate_rate'] ?? false);
        $reportingCurrencyCode = $recalculateRate
            ? ($group?->reporting_currency_code ?? $editor->default_currency_code)
            : $expense->reporting_currency_code;

        if ($reportingCurrencyCode === null) {
            throw ValidationException::withMessages([
                'currency_code' => 'A default reporting currency must be configured.',
            ]);
        }

        $reportingCurrency = Currency::query()->findOrFail($reportingCurrencyCode);
        $canRetainRate = ! $recalculateRate
            && $expense->currency_code === $baseCurrency->code
            && $expense->reporting_currency_code === $reportingCurrency->code;
        $resolvedRate = $canRetainRate
            ? new ResolvedExchangeRate(
                $expense->exchange_rate,
                $expense->exchange_rate_source,
                $expense->exchange_rate_effective_date === null
                    ? null
                    : CarbonImmutable::instance($expense->exchange_rate_effective_date),
            )
            : $this->exchangeRateResolver->resolve(
                $baseCurrency->code,
                $reportingCurrency->code,
                $data['occurred_at'],
                $group,
                $data['expense_rate'] ?? null,
            );
        $reportingAmountMinor = $resolvedRate->rate === null
            ? $data['amount_minor']
            : $this->moneyConverter->convert(
                $data['amount_minor'],
                $baseCurrency->minor_unit_factor,
                $reportingCurrency->minor_unit_factor,
                $resolvedRate->rate,
            );

        $allocations = [];
        $reportingAllocations = [];
        $splitType = $data['split_type'] ?? SplitType::Equal;

        if ($expenseType !== ExpenseType::Personal) {
            $splitValues = $this->participantValues($participants, $splitType);
            $allocations = $this->splitCalculator->calculate(
                $data['amount_minor'],
                $splitType,
                $splitValues,
                $this->participantKey($payerUserId, $payerPlaceholderId),
            );
            $reportingAllocations = $this->reportingSplitAllocator->allocate(
                $reportingAmountMinor,
                $allocations,
                $this->participantKey($payerUserId, $payerPlaceholderId),
            );
        }

        return DB::transaction(function () use (
            $editor,
            $expense,
            $data,
            $payerUserId,
            $payerPlaceholderId,
            $reportingCurrency,
            $reportingAmountMinor,
            $resolvedRate,
            $participants,
            $allocations,
            $reportingAllocations,
            $splitType,
            $recalculateRate,
        ): Expense {
            $expense->update([
                'payer_user_id' => $payerUserId,
                'payer_placeholder_id' => $payerPlaceholderId,
                'amount_minor' => $data['amount_minor'],
                'currency_code' => $data['currency_code'],
                'reporting_amount_minor' => $reportingAmountMinor,
                'reporting_currency_code' => $reportingCurrency->code,
                'exchange_rate' => $resolvedRate->rate,
                'exchange_rate_source' => $resolvedRate->source,
                'exchange_rate_effective_date' => $resolvedRate->effectiveDate,
                'description' => $data['description'],
                'category' => $data['category'] ?? null,
                'occurred_at' => $data['occurred_at'],
            ]);

            $expense->splits()->delete();

            foreach ($participants as $participant) {
                $participantKey = $this->participantKey(
                    $participant['user_id'] ?? null,
                    $participant['placeholder_id'] ?? null,
                );

                $expense->splits()->create([
                    'user_id' => $participant['user_id'] ?? null,
                    'placeholder_id' => $participant['placeholder_id'] ?? null,
                    'amount_owed_minor' => $allocations[$participantKey],
                    'reporting_amount_owed_minor' => $reportingAllocations[$participantKey],
                    'split_type' => $splitType,
                    'split_value' => $splitType === SplitType::Equal ? null : $participant['value'],
                ]);
            }

            ActivityLog::query()->create([
                'group_id' => $expense->group_id,
                'actor_id' => $editor->id,
                'subject_type' => $expense->getMorphClass(),
                'subject_id' => $expense->id,
                'event' => 'expense.updated',
                'metadata' => [
                    'exchange_rate' => $resolvedRate->rate,
                    'exchange_rate_source' => $resolvedRate->source->value,
                    'recalculated_rate' => $recalculateRate,
                ],
            ]);

            return $expense->load('splits');
        });
    }

    /**
     * @param  list<array{user_id?: int|null, placeholder_id?: int|null, value?: int}>  $participants
     */
    private function validateContext(
        User $editor,
        Expense $expense,
        ?Group $group,
        ?int $payerUserId,
        ?int $payerPlaceholderId,
        array $participants,
    ): void {
        $this->participantKey($payerUserId, $payerPlaceholderId);

        if ($expense->expense_type === ExpenseType::Personal) {
            if ($payerUserId !== $expense->created_by || $payerPlaceholderId !== null || $participants !== []) {
                throw ValidationException::withMessages([
                    'expense_type' => 'A personal expense must be paid by its creator and cannot contain splits.',
                ]);
            }

            return;
        }

        if ($participants === []) {
            throw new InvalidSplit('At least one split participant is required.');
        }

        if ($expense->expense_type === ExpenseType::Group) {
            if ($group === null || $group->archived_at !== null) {
                throw ValidationException::withMessages(['group' => 'An active group is required.']);
            }

            $activeMembers = $group->members()->whereNull('left_at')->get();
            $allowedKeys = $activeMembers->mapWithKeys(fn ($member): array => [
                $this->participantKey($member->user_id, $member->placeholder_id) => true,
            ]);

            if (! $allowedKeys->has("user:{$editor->id}")) {
                throw ValidationException::withMessages(['group' => 'The editor must be an active group member.']);
            }

            $requestedKeys = array_map(fn (array $participant): string => $this->participantKey(
                $participant['user_id'] ?? null,
                $participant['placeholder_id'] ?? null,
            ), $participants);
            $requestedKeys[] = $this->participantKey($payerUserId, $payerPlaceholderId);

            foreach ($requestedKeys as $requestedKey) {
                if (! $allowedKeys->has($requestedKey)) {
                    throw ValidationException::withMessages(['participants' => 'Every participant must be an active group member.']);
                }
            }

            return;
        }

        $participantKeys = array_map(fn (array $participant): string => $this->participantKey(
            $participant['user_id'] ?? null,
            $participant['placeholder_id'] ?? null,
        ), $participants);

        if (! in_array("user:{$expense->created_by}", $participantKeys, true)) {
            throw ValidationException::withMessages(['participants' => 'The expense creator must participate in a direct expense.']);
        }

        foreach ($participants as $participant) {
            $placeholderId = $participant['placeholder_id'] ?? null;

            if ($placeholderId !== null && ! Placeholder::query()
                ->whereKey($placeholderId)
                ->where('created_by', $expense->created_by)
                ->exists()) {
                throw ValidationException::withMessages(['participants' => 'A placeholder must belong to the expense creator.']);
            }
        }
    }

    /**
     * @param  list<array{user_id?: int|null, placeholder_id?: int|null, value?: int}>  $participants
     * @return array<string, int>
     */
    private function participantValues(array $participants, SplitType $splitType): array
    {
        $values = [];

        foreach ($participants as $participant) {
            $key = $this->participantKey(
                $participant['user_id'] ?? null,
                $participant['placeholder_id'] ?? null,
            );

            if (array_key_exists($key, $values)) {
                throw ValidationException::withMessages(['participants' => 'Each participant may only appear once.']);
            }

            if ($splitType !== SplitType::Equal && ! array_key_exists('value', $participant)) {
                throw new InvalidSplit('A split value is required for each participant.');
            }

            $values[$key] = $participant['value'] ?? 1;
        }

        return $values;
    }

    private function participantKey(?int $userId, ?int $placeholderId): string
    {
        if (($userId === null) === ($placeholderId === null)) {
            throw ValidationException::withMessages([
                'participants' => 'A participant must reference exactly one user or placeholder.',
            ]);
        }

        return $userId !== null ? "user:{$userId}" : "placeholder:{$placeholderId}";
    }
}
