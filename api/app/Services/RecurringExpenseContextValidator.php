<?php

namespace App\Services;

use App\Exceptions\InvalidSplit;
use App\ExpenseType;
use App\Models\Placeholder;
use App\Models\RecurringExpense;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RecurringExpenseContextValidator
{
    /**
     * @param  list<array{user_id?: int|null, placeholder_id?: int|null, value?: int}>  $participants
     */
    public function validate(
        User $editor,
        RecurringExpense $recurringExpense,
        ?int $payerUserId,
        ?int $payerPlaceholderId,
        array $participants,
    ): void {
        $this->participantKey($payerUserId, $payerPlaceholderId);

        if ($recurringExpense->expense_type === ExpenseType::Personal) {
            if ($payerUserId !== $recurringExpense->created_by || $payerPlaceholderId !== null || $participants !== []) {
                throw ValidationException::withMessages([
                    'expense_type' => 'A personal expense must be paid by its creator and cannot contain splits.',
                ]);
            }

            return;
        }

        if ($participants === []) {
            throw new InvalidSplit('At least one split participant is required.');
        }

        if ($recurringExpense->expense_type === ExpenseType::Group) {
            $group = $recurringExpense->group;
            if ($group === null || $group->archived_at !== null) {
                throw ValidationException::withMessages(['group' => 'An active group is required.']);
            }

            $allowedKeys = $group->members()
                ->whereNull('left_at')
                ->get()
                ->mapWithKeys(fn ($member): array => [
                    $this->participantKey($member->user_id, $member->placeholder_id) => true,
                ]);
            $requestedKeys = array_map(fn (array $participant): string => $this->participantKey(
                $participant['user_id'] ?? null,
                $participant['placeholder_id'] ?? null,
            ), $participants);
            $requestedKeys[] = $this->participantKey($payerUserId, $payerPlaceholderId);

            foreach ($requestedKeys as $requestedKey) {
                if (! $allowedKeys->has($requestedKey)) {
                    throw ValidationException::withMessages([
                        'participants' => 'Every participant must be an active group member.',
                    ]);
                }
            }

            return;
        }

        $participantKeys = array_map(fn (array $participant): string => $this->participantKey(
            $participant['user_id'] ?? null,
            $participant['placeholder_id'] ?? null,
        ), $participants);

        if (! in_array("user:{$recurringExpense->created_by}", $participantKeys, true)) {
            throw ValidationException::withMessages([
                'participants' => 'The schedule creator must participate in a direct expense.',
            ]);
        }

        foreach ($participants as $participant) {
            $participantUserId = $participant['user_id'] ?? null;
            $placeholderId = $participant['placeholder_id'] ?? null;

            if ($participantUserId !== null
                && $participantUserId !== $recurringExpense->created_by
                && ! $editor->isFriendsWith($participantUserId)) {
                throw ValidationException::withMessages([
                    'participants' => 'Registered participants in a direct expense must be accepted friends.',
                ]);
            }

            if ($placeholderId !== null && ! Placeholder::query()
                ->whereKey($placeholderId)
                ->where('created_by', $recurringExpense->created_by)
                ->exists()) {
                throw ValidationException::withMessages([
                    'participants' => 'A placeholder must belong to the schedule creator.',
                ]);
            }
        }
    }

    private function participantKey(?int $userId, ?int $placeholderId): string
    {
        if (($userId === null) === ($placeholderId === null)) {
            throw ValidationException::withMessages([
                'participants' => 'Select exactly one user or placeholder.',
            ]);
        }

        return $userId !== null ? "user:{$userId}" : "placeholder:{$placeholderId}";
    }
}
