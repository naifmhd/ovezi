<?php

namespace App\Policies;

use App\GroupMemberRole;
use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GroupPolicy
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
    public function view(User $user, Group $group): bool|Response
    {
        return $this->isActiveMember($user, $group)
            ? true
            : Response::denyAsNotFound();
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
    public function update(User $user, Group $group): bool|Response
    {
        return $this->manageResponse($user, $group);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Group $group): bool|Response
    {
        return $this->manageResponse($user, $group);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Group $group): bool|Response
    {
        return $this->manageResponse($user, $group);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Group $group): bool
    {
        return false;
    }

    private function isOwner(User $user, Group $group): bool
    {
        return $group->members()
            ->whereBelongsTo($user)
            ->where('role', GroupMemberRole::Owner->value)
            ->whereNull('left_at')
            ->exists();
    }

    private function isActiveMember(User $user, Group $group): bool
    {
        return $group->members()
            ->whereBelongsTo($user)
            ->whereNull('left_at')
            ->exists();
    }

    private function manageResponse(User $user, Group $group): bool|Response
    {
        if (! $this->isActiveMember($user, $group)) {
            return Response::denyAsNotFound();
        }

        return $this->isOwner($user, $group);
    }
}
