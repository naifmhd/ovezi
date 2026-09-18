<?php

namespace App\Actions;

use App\GroupMemberRole;
use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateGroup
{
    /**
     * @param  array{name: string, reporting_currency_code: string}  $attributes
     */
    public function execute(User $creator, array $attributes): Group
    {
        return DB::transaction(function () use ($creator, $attributes): Group {
            $group = Group::query()->create([
                ...$attributes,
                'created_by' => $creator->id,
            ]);

            $group->members()->create([
                'user_id' => $creator->id,
                'role' => GroupMemberRole::Owner,
                'joined_at' => now(),
            ]);

            ActivityLog::query()->create([
                'group_id' => $group->id,
                'actor_id' => $creator->id,
                'subject_type' => $group->getMorphClass(),
                'subject_id' => $group->id,
                'event' => 'group.created',
                'metadata' => [
                    'name' => $group->name,
                    'reporting_currency_code' => $group->reporting_currency_code,
                ],
            ]);

            return $group->loadCount('activeMembers');
        });
    }
}
