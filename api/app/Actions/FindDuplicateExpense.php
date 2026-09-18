<?php

namespace App\Actions;

use App\ExpenseType;
use App\Models\Expense;
use App\Models\Group;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class FindDuplicateExpense
{
    /**
     * @param  list<array{user_id?: int|null, placeholder_id?: int|null}>  $participants
     */
    public function execute(
        User $creator,
        ExpenseType $expenseType,
        ?Group $group,
        ?int $payerUserId,
        ?int $payerPlaceholderId,
        int $amountMinor,
        string $currencyCode,
        string $description,
        CarbonImmutable $occurredAt,
        array $participants,
    ): ?Expense {
        $participantKeys = $this->participantKeys($participants);
        $normalizedDescription = $this->normalizeDescription($description);

        return Expense::query()
            ->where('created_by', $creator->id)
            ->where('expense_type', $expenseType)
            ->where('group_id', $group?->id)
            ->where('payer_user_id', $payerUserId)
            ->where('payer_placeholder_id', $payerPlaceholderId)
            ->where('amount_minor', $amountMinor)
            ->where('currency_code', $currencyCode)
            ->whereDate('occurred_at', $occurredAt->toDateString())
            ->with('splits:id,expense_id,user_id,placeholder_id')
            ->latest('id')
            ->get()
            ->first(function (Expense $expense) use ($normalizedDescription, $participantKeys): bool {
                return $this->normalizeDescription($expense->description) === $normalizedDescription
                    && $this->participantKeys($expense->splits) === $participantKeys;
            });
    }

    /**
     * @param  iterable<array{user_id?: int|null, placeholder_id?: int|null}|object>  $participants
     * @return list<string>
     */
    private function participantKeys(iterable $participants): array
    {
        return Collection::make($participants)
            ->map(function (array|object $participant): string {
                $userId = is_array($participant) ? ($participant['user_id'] ?? null) : $participant->user_id;
                $placeholderId = is_array($participant)
                    ? ($participant['placeholder_id'] ?? null)
                    : $participant->placeholder_id;

                return $userId !== null ? "user:{$userId}" : "placeholder:{$placeholderId}";
            })
            ->sort()
            ->values()
            ->all();
    }

    private function normalizeDescription(string $description): string
    {
        return mb_strtolower((string) preg_replace('/\s+/', ' ', trim($description)));
    }
}
