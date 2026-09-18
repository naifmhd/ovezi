<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexActivityRequest;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class GroupActivityController extends Controller
{
    public function __invoke(IndexActivityRequest $request, Group $group): AnonymousResourceCollection
    {
        Gate::authorize('view', $group);

        return ActivityResource::collection(
            $group->activities()
                ->with('actor:id,name')
                ->latest('created_at')
                ->latest('id')
                ->paginate($request->integer('per_page', 20)),
        );
    }
}
