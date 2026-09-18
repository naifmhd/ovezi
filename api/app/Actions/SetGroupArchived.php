<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SetGroupArchived
{
    public function execute(User $actor, Group $group, bool $archived): Group
    {
        if (($group->archived_at !== null) === $archived) {
            return $group->loadCount('activeMembers');
        }

        return DB::transaction(function () use ($actor, $group, $archived): Group {
            $group->update([
                'archived_at' => $archived ? now() : null,
            ]);

            ActivityLog::query()->create([
                'group_id' => $group->id,
                'actor_id' => $actor->id,
                'subject_type' => $group->getMorphClass(),
                'subject_id' => $group->id,
                'event' => $archived ? 'group.archived' : 'group.reopened',
                'metadata' => null,
            ]);

            return $group->loadCount('activeMembers');
        });
    }
}
