<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AddGroupMember;
use App\Actions\RemoveGroupMember;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DestroyGroupMemberRequest;
use App\Http\Requests\Api\V1\StoreGroupMemberRequest;
use App\Http\Resources\Api\V1\GroupMemberResource;
use App\Models\Group;
use App\Models\GroupMember;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class GroupMemberController extends Controller
{
    public function store(
        StoreGroupMemberRequest $request,
        Group $group,
        AddGroupMember $addGroupMember,
    ): JsonResponse {
        $membership = $addGroupMember->execute(
            $request->user(),
            $group,
            $request->filled('user_id') ? $request->integer('user_id') : null,
            $request->filled('placeholder_id') ? $request->integer('placeholder_id') : null,
        );

        return GroupMemberResource::make($membership)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function destroy(
        DestroyGroupMemberRequest $request,
        Group $group,
        GroupMember $member,
        RemoveGroupMember $removeGroupMember,
    ): GroupMemberResource {
        return GroupMemberResource::make(
            $removeGroupMember->execute($request->user(), $group, $member),
        );
    }
}
