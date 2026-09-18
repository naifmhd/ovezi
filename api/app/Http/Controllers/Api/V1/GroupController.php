<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateGroup;
use App\Actions\UpdateGroup;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexGroupRequest;
use App\Http\Requests\Api\V1\StoreGroupRequest;
use App\Http\Requests\Api\V1\UpdateGroupRequest;
use App\Http\Resources\Api\V1\GroupResource;
use App\Models\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class GroupController extends Controller
{
    public function index(IndexGroupRequest $request): AnonymousResourceCollection
    {
        $status = $request->string('status')->toString();
        $groups = Group::query()
            ->whereHas('members', fn (Builder $query): Builder => $query
                ->whereBelongsTo($request->user())
                ->whereNull('left_at'))
            ->when($status === 'active', fn (Builder $query): Builder => $query->whereNull('archived_at'))
            ->when($status === 'archived', fn (Builder $query): Builder => $query->whereNotNull('archived_at'))
            ->withCount('activeMembers')
            ->latest('updated_at')
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return GroupResource::collection($groups);
    }

    public function store(StoreGroupRequest $request, CreateGroup $createGroup): JsonResponse
    {
        $group = $createGroup->execute(
            $request->user(),
            $request->safe()->only(['name', 'reporting_currency_code']),
        );

        return GroupResource::make($group)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Group $group): GroupResource
    {
        Gate::authorize('view', $group);

        $group->load([
            'activeMembers.user:id,name',
            'activeMembers.placeholder:id,name',
        ])->loadCount('activeMembers');

        return GroupResource::make($group);
    }

    public function update(UpdateGroupRequest $request, Group $group, UpdateGroup $updateGroup): GroupResource
    {
        $group = $updateGroup->execute(
            $request->user(),
            $group,
            $request->safe()->only(['name', 'reporting_currency_code']),
        );

        return GroupResource::make($group);
    }
}
