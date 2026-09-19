<?php

namespace App\Policies;

use App\ExpenseType;
use App\Models\RecurringExpense;
use App\Models\User;

class RecurringExpensePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RecurringExpense $recurringExpense): bool
    {
        if ($recurringExpense->expense_type === ExpenseType::Group) {
            return $recurringExpense->group?->members()
                ->whereBelongsTo($user)
                ->whereNull('left_at')
                ->exists() ?? false;
        }

        if ($recurringExpense->expense_type === ExpenseType::Personal) {
            return $recurringExpense->created_by === $user->id;
        }

        return $recurringExpense->created_by === $user->id
            || $recurringExpense->payer_user_id === $user->id
            || $recurringExpense->payerPlaceholder?->claimed_by === $user->id
            || $recurringExpense->splits()->whereBelongsTo($user)->exists()
            || $recurringExpense->splits()->whereHas(
                'placeholder',
                fn ($query) => $query->where('claimed_by', $user->id),
            )->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->canManage($user, $recurringExpense);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RecurringExpense $recurringExpense): bool
    {
        return $this->canManage($user, $recurringExpense);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RecurringExpense $recurringExpense): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RecurringExpense $recurringExpense): bool
    {
        return false;
    }

    private function canManage(User $user, RecurringExpense $recurringExpense): bool
    {
        if ($recurringExpense->created_by === $user->id) {
            return true;
        }

        return $recurringExpense->expense_type === ExpenseType::Group
            && $recurringExpense->group !== null
            && $user->can('update', $recurringExpense->group);
    }
}
