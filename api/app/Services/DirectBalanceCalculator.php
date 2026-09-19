<?php

namespace App\Services;

use App\ExpenseType;
use App\Models\Expense;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DirectBalanceCalculator
{
    /**
     * Positive balances mean the user is owed money; negative balances mean the user owes money.
     *
     * @return list<array{
     *     participant: array{key: string, user_id: int|null, placeholder_id: int|null, name: string},
     *     currency_code: string,
     *     balance_minor: int
     * }>
     */
    public function calculateFor(User $user): array
    {
        $balances = [];

        Expense::query()
            ->where('expense_type', ExpenseType::Direct)
            ->where(function (Builder $query) use ($user): void {
                $query->where('created_by', $user->id)
                    ->orWhere('payer_user_id', $user->id)
                    ->orWhereHas('payerPlaceholder', fn (Builder $placeholderQuery): Builder => $placeholderQuery
                        ->where('claimed_by', $user->id))
                    ->orWhereHas('splits', fn (Builder $splitQuery): Builder => $splitQuery
                        ->where('user_id', $user->id)
                        ->orWhereHas('placeholder', fn (Builder $placeholderQuery): Builder => $placeholderQuery
                            ->where('claimed_by', $user->id)));
            })
            ->with([
                'payerUser:id,name',
                'payerPlaceholder:id,name,claimed_by',
                'payerPlaceholder.claimedBy:id,name',
                'splits:id,expense_id,user_id,placeholder_id,reporting_amount_owed_minor',
                'splits.user:id,name',
                'splits.placeholder:id,name,claimed_by',
                'splits.placeholder.claimedBy:id,name',
            ])
            ->select(['id', 'payer_user_id', 'payer_placeholder_id', 'reporting_currency_code'])
            ->orderBy('id')
            ->each(function (Expense $expense) use ($user, &$balances): void {
                $payer = $this->participant(
                    $expense->payer_user_id,
                    $expense->payer_placeholder_id,
                    $expense->payerUser?->name,
                    $expense->payerPlaceholder?->name,
                    $expense->payerPlaceholder?->claimed_by,
                    $expense->payerPlaceholder?->claimedBy?->name,
                );

                foreach ($expense->splits as $split) {
                    $debtor = $this->participant(
                        $split->user_id,
                        $split->placeholder_id,
                        $split->user?->name,
                        $split->placeholder?->name,
                        $split->placeholder?->claimed_by,
                        $split->placeholder?->claimedBy?->name,
                    );

                    if ($payer['key'] === $debtor['key']) {
                        continue;
                    }

                    $this->applyTransfer(
                        $balances,
                        $user->id,
                        $payer,
                        $debtor,
                        $expense->reporting_currency_code,
                        $split->reporting_amount_owed_minor,
                    );
                }
            });

        Settlement::query()
            ->whereNull('group_id')
            ->where(function (Builder $query) use ($user): void {
                $query->where('from_user_id', $user->id)
                    ->orWhere('to_user_id', $user->id)
                    ->orWhereHas('fromPlaceholder', fn (Builder $placeholderQuery): Builder => $placeholderQuery
                        ->where('claimed_by', $user->id))
                    ->orWhereHas('toPlaceholder', fn (Builder $placeholderQuery): Builder => $placeholderQuery
                        ->where('claimed_by', $user->id));
            })
            ->with([
                'fromUser:id,name',
                'fromPlaceholder:id,name,claimed_by',
                'fromPlaceholder.claimedBy:id,name',
                'toUser:id,name',
                'toPlaceholder:id,name,claimed_by',
                'toPlaceholder.claimedBy:id,name',
            ])
            ->select([
                'id', 'from_user_id', 'from_placeholder_id', 'to_user_id', 'to_placeholder_id',
                'reporting_amount_minor', 'reporting_currency_code',
            ])
            ->orderBy('id')
            ->each(function (Settlement $settlement) use ($user, &$balances): void {
                $from = $this->participant(
                    $settlement->from_user_id,
                    $settlement->from_placeholder_id,
                    $settlement->fromUser?->name,
                    $settlement->fromPlaceholder?->name,
                    $settlement->fromPlaceholder?->claimed_by,
                    $settlement->fromPlaceholder?->claimedBy?->name,
                );
                $to = $this->participant(
                    $settlement->to_user_id,
                    $settlement->to_placeholder_id,
                    $settlement->toUser?->name,
                    $settlement->toPlaceholder?->name,
                    $settlement->toPlaceholder?->claimed_by,
                    $settlement->toPlaceholder?->claimedBy?->name,
                );

                $this->applyTransfer(
                    $balances,
                    $user->id,
                    $from,
                    $to,
                    $settlement->reporting_currency_code,
                    $settlement->reporting_amount_minor,
                );
            });

        ksort($balances);

        return array_values(array_filter($balances, fn (array $balance): bool => $balance['balance_minor'] !== 0));
    }

    /**
     * @param  array<string, array{participant: array{key: string, user_id: int|null, placeholder_id: int|null, name: string}, currency_code: string, balance_minor: int}>  $balances
     * @param  array{key: string, user_id: int|null, placeholder_id: int|null, name: string}  $from
     * @param  array{key: string, user_id: int|null, placeholder_id: int|null, name: string}  $to
     */
    private function applyTransfer(
        array &$balances,
        int $userId,
        array $from,
        array $to,
        string $currencyCode,
        int $amountMinor,
    ): void {
        $userKey = "user:{$userId}";

        if ($from['key'] === $userKey) {
            $this->adjust($balances, $to, $currencyCode, $amountMinor);
        } elseif ($to['key'] === $userKey) {
            $this->adjust($balances, $from, $currencyCode, -$amountMinor);
        }
    }

    /**
     * @param  array<string, array{participant: array{key: string, user_id: int|null, placeholder_id: int|null, name: string}, currency_code: string, balance_minor: int}>  $balances
     * @param  array{key: string, user_id: int|null, placeholder_id: int|null, name: string}  $participant
     */
    private function adjust(array &$balances, array $participant, string $currencyCode, int $amountMinor): void
    {
        $ledgerKey = "{$currencyCode}|{$participant['key']}";
        $balances[$ledgerKey] ??= [
            'participant' => $participant,
            'currency_code' => $currencyCode,
            'balance_minor' => 0,
        ];
        $balances[$ledgerKey]['balance_minor'] += $amountMinor;
    }

    /** @return array{key: string, user_id: int|null, placeholder_id: int|null, name: string} */
    private function participant(
        ?int $userId,
        ?int $placeholderId,
        ?string $userName,
        ?string $placeholderName,
        ?int $claimedUserId,
        ?string $claimedUserName,
    ): array {
        if ($userId !== null) {
            return [
                'key' => "user:{$userId}",
                'user_id' => $userId,
                'placeholder_id' => null,
                'name' => $userName ?? 'Unknown',
            ];
        }

        if ($claimedUserId !== null) {
            return [
                'key' => "user:{$claimedUserId}",
                'user_id' => $claimedUserId,
                'placeholder_id' => null,
                'name' => $claimedUserName ?? $placeholderName ?? 'Unknown',
            ];
        }

        if ($placeholderId === null) {
            throw new \InvalidArgumentException('Select exactly one user or placeholder.');
        }

        return [
            'key' => "placeholder:{$placeholderId}",
            'user_id' => null,
            'placeholder_id' => $placeholderId,
            'name' => $placeholderName ?? 'Unknown',
        ];
    }
}
