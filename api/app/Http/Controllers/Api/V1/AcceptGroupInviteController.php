<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\AcceptGroupInvite;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AcceptGroupInviteRequest;
use App\Http\Resources\Api\V1\GroupMemberResource;

class AcceptGroupInviteController extends Controller
{
    public function __invoke(
        AcceptGroupInviteRequest $request,
        AcceptGroupInvite $acceptGroupInvite,
    ): GroupMemberResource {
        return GroupMemberResource::make(
            $acceptGroupInvite->execute($request->user(), $request->validated('token')),
        );
    }
}
