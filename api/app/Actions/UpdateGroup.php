<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateGroup
{
    /**
     * @param  array{name?: string, reporting_currency_code?: string}  $attributes
     */
    public function execute(User $actor, Group $group, array $attributes): Group
    {
        if ($group->archived_at !== null) {
            throw ValidationException::withMessages([
                'group' => 'Reopen the group before changing its settings.',
            ]);
        }

        return DB::transaction(function () use ($actor, $group, $attributes): Group {
            $original = Arr::only($group->getAttributes(), array_keys($attributes));
            $group->update($attributes);
            $changes = Arr::only($group->getChanges(), array_keys($attributes));

            if ($changes !== []) {
                ActivityLog::query()->create([
                    'group_id' => $group->id,
                    'actor_id' => $actor->id,
                    'subject_type' => $group->getMorphClass(),
                    'subject_id' => $group->id,
                    'event' => 'group.updated',
                    'metadata' => [
                        'before' => Arr::only($original, array_keys($changes)),
                        'after' => $changes,
                    ],
                ]);
            }

            return $group->loadCount('activeMembers');
        });
    }
}
