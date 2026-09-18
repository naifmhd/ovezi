<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SetGroupArchived;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\GroupResource;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ArchivedGroupController extends Controller
{
    public function store(Request $request, Group $group, SetGroupArchived $setGroupArchived): GroupResource
    {
        Gate::authorize('update', $group);

        return GroupResource::make(
            $setGroupArchived->execute($request->user(), $group, true),
        );
    }

    public function destroy(Request $request, Group $group, SetGroupArchived $setGroupArchived): GroupResource
    {
        Gate::authorize('update', $group);

        return GroupResource::make(
            $setGroupArchived->execute($request->user(), $group, false),
        );
    }
}
