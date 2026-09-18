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
use App\Services\SplitCalculator;
use App\SplitType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateExpense
{
    public function __construct(
        private readonly SplitCalculator $splitCalculator,
        private readonly ExchangeRateResolver $exchangeRateResolver,
        private readonly MoneyConverter $moneyConverter,
    ) {}

    /**
     * @param array{
     *     expense_type: ExpenseType,
     *     group?: Group|null,
     *     payer_user_id?: int|null,
     *     payer_placeholder_id?: int|null,
     *     amount_minor: int,
     *     currency_code: string,
     *     description: string,
     *     category?: string|null,
     *     receipt_image_path?: string|null,
     *     occurred_at: CarbonImmutable,
     *     split_type?: SplitType,
     *     participants?: list<array{user_id?: int|null, placeholder_id?: int|null, value?: int}>,
     *     expense_rate?: string|null
     * } $data
     */
    public function execute(User $creator, array $data): Expense
    {
        if ($data['amount_minor'] <= 0) {
            throw ValidationException::withMessages(['amount_minor' => 'The expense amount must be greater than zero.']);
        }

        $expenseType = $data['expense_type'];
        $group = $data['group'] ?? null;
        $payerUserId = $data['payer_user_id'] ?? null;
        $payerPlaceholderId = $data['payer_placeholder_id'] ?? null;
        $participants = $data['participants'] ?? [];

        $this->validateContext(
            $creator,
            $expenseType,
            $group,
            $payerUserId,
            $payerPlaceholderId,
            $participants,
        );

        $baseCurrency = Currency::query()->findOrFail($data['currency_code']);
        $reportingCurrencyCode = $group?->reporting_currency_code ?? $creator->default_currency_code;

        if ($reportingCurrencyCode === null) {
            throw ValidationException::withMessages([
                'currency_code' => 'A default reporting currency must be configured.',
            ]);
        }

        $reportingCurrency = Currency::query()->findOrFail($reportingCurrencyCode);
        $resolvedRate = $this->exchangeRateResolver->resolve(
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
        $splitType = $data['split_type'] ?? SplitType::Equal;

        if ($expenseType !== ExpenseType::Personal) {
            $splitValues = $this->participantValues($participants, $splitType);
            $allocations = $this->splitCalculator->calculate(
                $data['amount_minor'],
                $splitType,
                $splitValues,
                $this->participantKey($payerUserId, $payerPlaceholderId),
            );
        }

        return DB::transaction(function () use (
            $creator,
            $data,
            $expenseType,
            $group,
            $payerUserId,
            $payerPlaceholderId,
            $reportingCurrency,
            $reportingAmountMinor,
            $resolvedRate,
            $participants,
            $allocations,
            $splitType,
        ): Expense {
            $expense = Expense::query()->create([
                'expense_type' => $expenseType,
                'group_id' => $group?->id,
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
                'receipt_image_path' => $data['receipt_image_path'] ?? null,
                'occurred_at' => $data['occurred_at'],
                'created_by' => $creator->id,
            ]);

            foreach ($participants as $participant) {
                $participantKey = $this->participantKey(
                    $participant['user_id'] ?? null,
                    $participant['placeholder_id'] ?? null,
                );

                $expense->splits()->create([
                    'user_id' => $participant['user_id'] ?? null,
                    'placeholder_id' => $participant['placeholder_id'] ?? null,
                    'amount_owed_minor' => $allocations[$participantKey],
                    'split_type' => $splitType,
                    'split_value' => $splitType === SplitType::Equal ? null : $participant['value'],
                ]);
            }

            ActivityLog::query()->create([
                'group_id' => $group?->id,
                'actor_id' => $creator->id,
                'subject_type' => $expense->getMorphClass(),
                'subject_id' => $expense->id,
                'event' => 'expense.created',
                'metadata' => [
                    'exchange_rate' => $resolvedRate->rate,
                    'exchange_rate_source' => $resolvedRate->source->value,
                ],
            ]);

            return $expense->load('splits');
        });
    }

    /**
     * @param  list<array{user_id?: int|null, placeholder_id?: int|null, value?: int}>  $participants
     */
    private function validateContext(
        User $creator,
        ExpenseType $expenseType,
        ?Group $group,
        ?int $payerUserId,
        ?int $payerPlaceholderId,
        array $participants,
    ): void {
        $this->participantKey($payerUserId, $payerPlaceholderId);

        if ($expenseType === ExpenseType::Personal) {
            if ($group !== null || $payerUserId !== $creator->id || $payerPlaceholderId !== null || $participants !== []) {
                throw ValidationException::withMessages([
                    'expense_type' => 'A personal expense must be paid by its creator and cannot contain splits.',
                ]);
            }

            return;
        }

        if ($participants === []) {
            throw new InvalidSplit('At least one split participant is required.');
        }

        if ($expenseType === ExpenseType::Group) {
            if ($group === null || $group->archived_at !== null) {
                throw ValidationException::withMessages(['group' => 'An active group is required.']);
            }

            $activeMembers = $group->members()->whereNull('left_at')->get();
            $allowedKeys = $activeMembers->mapWithKeys(fn ($member): array => [
                $this->participantKey($member->user_id, $member->placeholder_id) => true,
            ]);

            if (! $allowedKeys->has("user:{$creator->id}")) {
                throw ValidationException::withMessages(['group' => 'The creator must be an active group member.']);
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

        if ($group !== null) {
            throw ValidationException::withMessages(['group' => 'Direct expenses cannot belong to a group.']);
        }

        $participantKeys = array_map(fn (array $participant): string => $this->participantKey(
            $participant['user_id'] ?? null,
            $participant['placeholder_id'] ?? null,
        ), $participants);

        if (! in_array("user:{$creator->id}", $participantKeys, true)) {
            throw ValidationException::withMessages(['participants' => 'The creator must participate in a direct expense.']);
        }

        foreach ($participants as $participant) {
            $placeholderId = $participant['placeholder_id'] ?? null;

            if ($placeholderId !== null && ! Placeholder::query()
                ->whereKey($placeholderId)
                ->where('created_by', $creator->id)
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
