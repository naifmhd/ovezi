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
use App\Services\ExpenseAllocation;
use App\Services\MoneyConverter;
use App\Services\ResolvedExchangeRate;
use App\SplitType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateExpense
{
    public function __construct(
        private readonly ExpenseAllocation $expenseAllocation,
        private readonly ExchangeRateResolver $exchangeRateResolver,
        private readonly MoneyConverter $moneyConverter,
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
     *     participants?: list<array{user_id?: int|null, placeholder_id?: int|null, value?: int, included_in_split?: bool, amount_paid_minor?: int|null}>,
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

        $splitType = $data['split_type'] ?? SplitType::Equal;
        $splitRows = $expenseType === ExpenseType::Personal ? [] : $this->expenseAllocation->calculate(
            $data['amount_minor'], $reportingAmountMinor, $splitType, $participants, $payerUserId, $payerPlaceholderId,
        );

        return DB::transaction(function () use (
            $editor,
            $expense,
            $data,
            $payerUserId,
            $payerPlaceholderId,
            $reportingCurrency,
            $reportingAmountMinor,
            $resolvedRate,
            $splitRows,
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

            foreach ($splitRows as $splitRow) {
                $expense->splits()->create($splitRow);
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
     * @param  list<array{user_id?: int|null, placeholder_id?: int|null, value?: int, included_in_split?: bool, amount_paid_minor?: int|null}>  $participants
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

        $participants[] = ['user_id' => $payerUserId, 'placeholder_id' => $payerPlaceholderId];

        foreach ($participants as $participant) {
            $participantUserId = $participant['user_id'] ?? null;
            $placeholderId = $participant['placeholder_id'] ?? null;

            if ($participantUserId !== null
                && $participantUserId !== $expense->created_by
                && ! $editor->isFriendsWith($participantUserId)) {
                throw ValidationException::withMessages([
                    'participants' => 'Registered participants in a direct expense must be accepted friends.',
                ]);
            }

            if ($placeholderId !== null && ! Placeholder::query()
                ->whereKey($placeholderId)
                ->where('created_by', $expense->created_by)
                ->exists()) {
                throw ValidationException::withMessages(['participants' => 'A placeholder must belong to the expense creator.']);
            }
        }
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
