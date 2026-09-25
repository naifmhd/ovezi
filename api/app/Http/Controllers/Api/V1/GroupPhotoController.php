<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DeleteGroupPhoto;
use App\Actions\StoreGroupPhoto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreGroupPhotoRequest;
use App\Http\Resources\Api\V1\GroupResource;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GroupPhotoController extends Controller
{
    public function show(Group $group): StreamedResponse
    {
        Gate::authorize('view', $group);
        abort_if($group->photo_path === null, 404);

        $disk = Storage::disk((string) config('filesystems.group_photos_disk'));
        abort_unless($disk->exists($group->photo_path), 404);

        return $disk->response($group->photo_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function store(
        StoreGroupPhotoRequest $request,
        Group $group,
        StoreGroupPhoto $storeGroupPhoto,
    ): GroupResource {
        $updatedGroup = $storeGroupPhoto->execute(
            $request->user(),
            $group,
            $request->file('photo'),
        );

        return GroupResource::make($this->loadGroup($updatedGroup));
    }

    public function destroy(
        Request $request,
        Group $group,
        DeleteGroupPhoto $deleteGroupPhoto,
    ): Response {
        Gate::authorize('update', $group);
        $deleteGroupPhoto->execute($request->user(), $group);

        return response()->noContent();
    }

    private function loadGroup(Group $group): Group
    {
        return $group->load([
            'activeMembers.user:id,name,deleted_at',
            'activeMembers.placeholder:id,name',
        ])->loadCount('activeMembers');
    }
}
