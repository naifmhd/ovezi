<?php

namespace App\Policies;

use App\Models\Placeholder;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PlaceholderPolicy
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
    public function view(User $user, Placeholder $placeholder): bool|Response
    {
        return $this->owns($user, $placeholder);
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
    public function update(User $user, Placeholder $placeholder): bool|Response
    {
        return $this->owns($user, $placeholder);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Placeholder $placeholder): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Placeholder $placeholder): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Placeholder $placeholder): bool
    {
        return false;
    }

    private function owns(User $user, Placeholder $placeholder): bool|Response
    {
        return $placeholder->created_by === $user->id
            ? true
            : Response::denyAsNotFound();
    }
}
