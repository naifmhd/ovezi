<?php

namespace App\Policies;

use App\ExpenseType;
use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
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
    public function view(User $user, Expense $expense): bool
    {
        if ($expense->expense_type === ExpenseType::Group) {
            return $expense->group?->members()
                ->whereBelongsTo($user)
                ->whereNull('left_at')
                ->exists() ?? false;
        }

        if ($expense->expense_type === ExpenseType::Personal) {
            return $expense->created_by === $user->id;
        }

        return $expense->created_by === $user->id
            || $expense->payer_user_id === $user->id
            || $expense->payerPlaceholder?->claimed_by === $user->id
            || $expense->splits()->whereBelongsTo($user)->exists()
            || $expense->splits()->whereHas(
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
    public function update(User $user, Expense $expense): bool
    {
        return $this->canManage($user, $expense);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Expense $expense): bool
    {
        return $this->canManage($user, $expense);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Expense $expense): bool
    {
        return $this->canManage($user, $expense);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Expense $expense): bool
    {
        return false;
    }

    private function canManage(User $user, Expense $expense): bool
    {
        if ($expense->created_by === $user->id) {
            return true;
        }

        if ($expense->expense_type !== ExpenseType::Group) {
            return false;
        }

        return $expense->group !== null
            && $user->can('update', $expense->group);
    }
}
