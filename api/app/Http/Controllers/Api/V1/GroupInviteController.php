<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateGroupInvite;
use App\Actions\RevokeGroupInvite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreGroupInviteRequest;
use App\Http\Resources\Api\V1\GroupInviteResource;
use App\Models\Group;
use App\Models\GroupInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class GroupInviteController extends Controller
{
    public function index(Group $group): AnonymousResourceCollection
    {
        Gate::authorize('update', $group);

        return GroupInviteResource::collection(
            $group->invites()->latest('id')->paginate(20),
        );
    }

    public function store(
        StoreGroupInviteRequest $request,
        Group $group,
        CreateGroupInvite $createGroupInvite,
    ): JsonResponse {
        $result = $createGroupInvite->execute(
            $request->user(),
            $group,
            $request->validated('invited_email'),
        );

        return GroupInviteResource::make($result['invite'])
            ->additional(['meta' => ['token' => $result['token']]])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(
        Group $group,
        GroupInvite $invite,
        RevokeGroupInvite $revokeGroupInvite,
    ): GroupInviteResource {
        Gate::authorize('update', $group);

        abort_unless($invite->group_id === $group->id, Response::HTTP_NOT_FOUND);

        return GroupInviteResource::make($revokeGroupInvite->execute($invite));
    }
}
