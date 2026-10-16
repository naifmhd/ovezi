<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\GroupResource;
use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\RecurringExpense;
use App\Services\GroupBalanceCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class GroupCleanupController extends Controller
{
    public function destroy(Request $request, Group $group, GroupBalanceCalculator $balances): JsonResponse
    {
        Gate::authorize('delete', $group);
        DB::transaction(function () use ($request, $group, $balances): void {
            $group = Group::query()->lockForUpdate()->findOrFail($group->id);
            if (collect($balances->calculate($group))->contains(fn (int $balance): bool => $balance !== 0)) {
                throw ValidationException::withMessages(['group' => 'Settle every member’s balance before deleting this group. You can archive it instead.']);
            }
            if (RecurringExpense::query()->where('group_id', $group->id)->whereNull('canceled_at')->whereNotNull('next_occurrence_on')->exists()) {
                throw ValidationException::withMessages(['group' => 'Cancel recurring expenses before deleting this group, including paused schedules.']);
            }
            $group->update(['archived_at' => $group->archived_at ?? now()]);
            $group->invites()->whereNull('accepted_at')->update(['revoked_at' => now()]);
            ActivityLog::create(['group_id' => $group->id, 'actor_id' => $request->user()->id, 'subject_type' => Group::class, 'subject_id' => $group->id, 'event' => 'group.deleted']);
            $group->delete();
        });

        return response()->json(['message' => 'Group deleted for everyone. The owner can restore it from Deleted groups.']);
    }

    public function restore(Request $request, int $group): GroupResource
    {
        $record = DB::transaction(function () use ($request, $group): Group {
            $record = Group::withTrashed()->lockForUpdate()->findOrFail($group);
            Gate::authorize('restore', $record);
            if ($record->trashed()) {
                $record->restore();
                ActivityLog::create(['group_id' => $record->id, 'actor_id' => $request->user()->id, 'subject_type' => Group::class, 'subject_id' => $record->id, 'event' => 'group.restored']);
            }

            return $record;
        });

        return GroupResource::make($record);
    }

    public function hidden(Request $request, Group $group): JsonResponse
    {
        Gate::authorize('view', $group);
        $data = $request->validate(['hidden' => ['required', 'boolean']]);
        $group->members()->where('user_id', $request->user()->id)->whereNull('left_at')->update(['hidden_at' => $data['hidden'] ? now() : null]);

        return response()->json(['message' => $data['hidden'] ? 'Group hidden only for you.' : 'Group is visible again.']);
    }
}
