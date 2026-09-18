<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AcceptFriendRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FriendshipResource;
use App\Models\Friendship;
use Illuminate\Http\Request;

class AcceptFriendRequestController extends Controller
{
    public function __invoke(
        Request $request,
        Friendship $friendship,
        AcceptFriendRequest $acceptFriendRequest,
    ): FriendshipResource {
        return FriendshipResource::make(
            $acceptFriendRequest->execute($request->user(), $friendship),
        );
    }
}
