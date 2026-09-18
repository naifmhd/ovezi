<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TransferGroupOwnership;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\TransferGroupOwnershipRequest;
use App\Http\Resources\Api\V1\GroupResource;
use App\Models\Group;

class TransferGroupOwnershipController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        TransferGroupOwnershipRequest $request,
        Group $group,
        TransferGroupOwnership $transferGroupOwnership,
    ): GroupResource {
        return GroupResource::make(
            $transferGroupOwnership->execute($request->user(), $group, $request->integer('user_id')),
        );
    }
}
