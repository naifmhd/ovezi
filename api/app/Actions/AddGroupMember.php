<?php

namespace App\Actions;

use App\GroupMemberRole;
use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Placeholder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AddGroupMember
{
    public function execute(
        User $actor,
        Group $group,
        ?int $userId,
        ?int $placeholderId,
    ): GroupMember {
        return DB::transaction(function () use ($actor, $group, $userId, $placeholderId): GroupMember {
            $lockedGroup = Group::query()->lockForUpdate()->findOrFail($group->id);

            if ($lockedGroup->archived_at !== null) {
                throw ValidationException::withMessages([
                    'group' => 'Reopen the group before adding members.',
                ]);
            }

            if ($placeholderId !== null) {
                $placeholder = Placeholder::query()->findOrFail($placeholderId);

                if ($placeholder->created_by !== $actor->id) {
                    throw ValidationException::withMessages([
                        'placeholder_id' => 'You can only add a placeholder that you created.',
                    ]);
                }

                if ($placeholder->claimed_by !== null) {
                    throw ValidationException::withMessages([
                        'placeholder_id' => 'Add the claimed user account instead of this placeholder.',
                    ]);
                }
            }

            $membershipQuery = $lockedGroup->members()->lockForUpdate();
            $membershipQuery->when(
                $userId !== null,
                fn ($query) => $query->where('user_id', $userId),
                fn ($query) => $query->where('placeholder_id', $placeholderId),
            );
            $membership = $membershipQuery->first();

            if ($membership?->left_at === null && $membership !== null) {
                throw ValidationException::withMessages([
                    'member' => 'This person is already an active group member.',
                ]);
            }

            $event = 'member.joined';

            if ($membership === null) {
                $membership = $lockedGroup->members()->create([
                    'user_id' => $userId,
                    'placeholder_id' => $placeholderId,
                    'role' => GroupMemberRole::Member,
                    'joined_at' => now(),
                ]);
            } else {
                $membership->update(['left_at' => null]);
                $event = 'member.rejoined';
            }

            ActivityLog::query()->create([
                'group_id' => $lockedGroup->id,
                'actor_id' => $actor->id,
                'subject_type' => $membership->getMorphClass(),
                'subject_id' => $membership->id,
                'event' => $event,
                'metadata' => [
                    'user_id' => $userId,
                    'placeholder_id' => $placeholderId,
                ],
            ]);

            return $membership->load(['user:id,name', 'placeholder:id,name']);
        });
    }
}
