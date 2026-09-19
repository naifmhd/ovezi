<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeleteGroupPhoto
{
    public function execute(User $actor, Group $group): void
    {
        if ($group->archived_at !== null) {
            throw ValidationException::withMessages([
                'group' => 'Reopen the group before changing its photo.',
            ]);
        }

        $path = $group->photo_path;

        if ($path === null) {
            return;
        }

        DB::transaction(function () use ($actor, $group): void {
            $group->update(['photo_path' => null]);

            ActivityLog::query()->create([
                'group_id' => $group->id,
                'actor_id' => $actor->id,
                'subject_type' => $group->getMorphClass(),
                'subject_id' => $group->id,
                'event' => 'group.updated',
                'metadata' => ['photo' => 'removed'],
            ]);
        });

        Storage::disk((string) config('filesystems.group_photos_disk'))->delete($path);
    }
}
