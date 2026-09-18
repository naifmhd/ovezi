<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\RequestFriend;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreFriendRequest;
use App\Http\Resources\Api\V1\FriendshipResource;
use App\Models\Friendship;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class FriendshipController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $friendships = Friendship::query()
            ->where(fn (Builder $query): Builder => $query
                ->where('user_id', $request->user()->id)
                ->orWhere('friend_id', $request->user()->id))
            ->with(['user:id,name,email', 'friend:id,name,email'])
            ->latest('updated_at')
            ->get();

        return FriendshipResource::collection($friendships);
    }

    public function store(StoreFriendRequest $request, RequestFriend $requestFriend): JsonResponse
    {
        return FriendshipResource::make(
            $requestFriend->execute($request->user(), $request->string('email')->toString()),
        )->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Friendship $friend): Response
    {
        if (! in_array($request->user()->id, [$friend->user_id, $friend->friend_id], true)) {
            abort(404);
        }

        $friend->delete();

        return response()->noContent();
    }
}
