<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateNotificationPreferenceRequest;
use App\Http\Resources\Api\V1\NotificationPreferenceResource;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function show(Request $request): NotificationPreferenceResource
    {
        $user = $this->loadUserGroups($request);
        $preference = $user->notificationPreference()->firstOrNew();
        $preference->setRelation('user', $user);

        return NotificationPreferenceResource::make($preference);
    }

    public function update(UpdateNotificationPreferenceRequest $request): JsonResponse
    {
        $preference = NotificationPreference::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $request->safe()->only(['expense_created', 'payment_received', 'settle_up_reminders']),
        );
        $preference->setRelation('user', $this->loadUserGroups($request));

        return NotificationPreferenceResource::make($preference)
            ->response()
            ->setStatusCode(200);
    }

    private function loadUserGroups(Request $request): User
    {
        return $request->user()->load([
            'groupMemberships' => fn ($query) => $query
                ->whereNull('left_at')
                ->whereHas('group')
                ->with('group:id,name'),
        ]);
    }
}
