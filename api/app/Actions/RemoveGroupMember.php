<?php

namespace App\Actions;

use App\GroupMemberRole;
use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\GroupBalanceCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveGroupMember
{
    public function __construct(private readonly GroupBalanceCalculator $balanceCalculator) {}

    public function execute(User $actor, Group $group, GroupMember $member): GroupMember
    {
        if ($group->archived_at !== null) {
            throw ValidationException::withMessages([
                'group' => 'Reopen the group before removing a member.',
            ]);
        }

        if ($member->left_at !== null) {
            throw ValidationException::withMessages(['member' => 'This member has already left the group.']);
        }

        if ($member->role === GroupMemberRole::Owner) {
            throw ValidationException::withMessages([
                'member' => 'The group owner cannot be removed.',
            ]);
        }

        $memberKey = $this->balanceCalculator->participantKey($member->user_id, $member->placeholder_id);
        $balance = $this->balanceCalculator->calculate($group)[$memberKey] ?? 0;

        if ($balance !== 0) {
            throw ValidationException::withMessages([
                'member' => 'Settle this member’s balance before removing them.',
            ]);
        }

        return DB::transaction(function () use ($actor, $group, $member): GroupMember {
            $member->update(['left_at' => now()]);

            ActivityLog::query()->create([
                'group_id' => $group->id,
                'actor_id' => $actor->id,
                'subject_type' => $member->getMorphClass(),
                'subject_id' => $member->id,
                'event' => 'member.removed',
                'metadata' => [
                    'user_id' => $member->user_id,
                    'placeholder_id' => $member->placeholder_id,
                ],
            ]);

            return $member->refresh()->load(['user:id,name', 'placeholder:id,name']);
        });
    }
}
