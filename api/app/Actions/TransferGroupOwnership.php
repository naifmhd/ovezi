<?php

namespace App\Actions;

use App\GroupMemberRole;
use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferGroupOwnership
{
    public function execute(User $actor, Group $group, int $targetUserId): Group
    {
        if ($group->archived_at !== null) {
            throw ValidationException::withMessages([
                'group' => 'Reopen the group before transferring ownership.',
            ]);
        }

        return DB::transaction(function () use ($actor, $group, $targetUserId): Group {
            $members = $group->members()->whereNull('left_at')->lockForUpdate()->get();
            $currentOwner = $members->first(fn (GroupMember $member): bool => $member->user_id === $actor->id
                && $member->role === GroupMemberRole::Owner);
            $newOwner = $members->first(fn (GroupMember $member): bool => $member->user_id === $targetUserId);

            if ($currentOwner === null) {
                throw ValidationException::withMessages(['group' => 'Only the current owner can transfer ownership.']);
            }

            if ($newOwner === null) {
                throw ValidationException::withMessages([
                    'user_id' => 'The new owner must be an active registered group member.',
                ]);
            }

            $currentOwner->update(['role' => GroupMemberRole::Member]);
            $newOwner->update(['role' => GroupMemberRole::Owner]);

            ActivityLog::query()->create([
                'group_id' => $group->id,
                'actor_id' => $actor->id,
                'subject_type' => $group->getMorphClass(),
                'subject_id' => $group->id,
                'event' => 'group.ownership_transferred',
                'metadata' => [
                    'from_user_id' => $actor->id,
                    'to_user_id' => $targetUserId,
                ],
            ]);

            return $group->load([
                'activeMembers.user:id,name,deleted_at',
                'activeMembers.placeholder:id,name',
            ])->loadCount('activeMembers');
        });
    }
}
