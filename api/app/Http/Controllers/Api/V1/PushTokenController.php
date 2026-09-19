<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DestroyPushTokenRequest;
use App\Http\Requests\Api\V1\StorePushTokenRequest;
use App\Models\PushToken;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class PushTokenController extends Controller
{
    public function store(StorePushTokenRequest $request): JsonResponse
    {
        $data = $request->safe()->only(['expo_push_token', 'platform', 'device_name']);
        $pushToken = PushToken::query()->updateOrCreate(
            ['expo_push_token' => $data['expo_push_token']],
            [
                'user_id' => $request->user()->id,
                'platform' => $data['platform'],
                'device_name' => $data['device_name'] ?? null,
                'last_seen_at' => now(),
                'revoked_at' => null,
            ],
        );

        return response()->json([
            'data' => [
                'id' => $pushToken->id,
                'platform' => $pushToken->platform,
                'device_name' => $pushToken->device_name,
                'last_seen_at' => $pushToken->last_seen_at,
            ],
        ], $pushToken->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function destroy(DestroyPushTokenRequest $request): Response
    {
        PushToken::query()
            ->whereBelongsTo($request->user())
            ->where('expo_push_token', $request->string('expo_push_token')->toString())
            ->update(['revoked_at' => now()]);

        return response()->noContent();
    }
}
