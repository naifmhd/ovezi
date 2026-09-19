<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GroupNotificationMuteController extends Controller
{
    public function store(Request $request, Group $group): JsonResponse
    {
        return $this->setMuted($request, $group, true);
    }

    public function destroy(Request $request, Group $group): JsonResponse
    {
        return $this->setMuted($request, $group, false);
    }

    private function setMuted(Request $request, Group $group, bool $muted): JsonResponse
    {
        Gate::authorize('view', $group);

        $membership = $group->activeMembers()->whereBelongsTo($request->user())->firstOrFail();
        $membership->update(['notifications_muted_at' => $muted ? now() : null]);

        return response()->json([
            'data' => [
                'group_id' => $group->id,
                'muted' => $muted,
            ],
        ]);
    }
}
