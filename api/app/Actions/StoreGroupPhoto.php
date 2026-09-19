<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class StoreGroupPhoto
{
    public function execute(User $actor, Group $group, UploadedFile $photo): Group
    {
        if ($group->archived_at !== null) {
            throw ValidationException::withMessages([
                'group' => 'Reopen the group before changing its photo.',
            ]);
        }

        $diskName = (string) config('filesystems.group_photos_disk');
        $newPath = $photo->store("group-photos/{$group->id}", $diskName);

        if (! is_string($newPath)) {
            throw new RuntimeException('The group photo could not be stored.');
        }

        $oldPath = $group->photo_path;

        try {
            DB::transaction(function () use ($actor, $group, $newPath): void {
                $group->update(['photo_path' => $newPath]);

                ActivityLog::query()->create([
                    'group_id' => $group->id,
                    'actor_id' => $actor->id,
                    'subject_type' => $group->getMorphClass(),
                    'subject_id' => $group->id,
                    'event' => 'group.updated',
                    'metadata' => ['photo' => 'uploaded'],
                ]);
            });
        } catch (Throwable $exception) {
            Storage::disk($diskName)->delete($newPath);

            throw $exception;
        }

        if ($oldPath !== null && $oldPath !== $newPath) {
            Storage::disk($diskName)->delete($oldPath);
        }

        return $group->refresh();
    }
}
