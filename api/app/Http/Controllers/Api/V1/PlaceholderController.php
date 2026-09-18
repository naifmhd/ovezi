<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreatePlaceholder;
use App\Actions\UpdatePlaceholder;
use App\ContactType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePlaceholderRequest;
use App\Http\Requests\Api\V1\UpdatePlaceholderRequest;
use App\Http\Resources\Api\V1\PlaceholderResource;
use App\Models\Placeholder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class PlaceholderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Placeholder::class);

        $placeholders = $request->user()
            ->createdPlaceholders()
            ->latest('updated_at')
            ->latest('id')
            ->paginate(20);

        return PlaceholderResource::collection($placeholders);
    }

    public function store(StorePlaceholderRequest $request, CreatePlaceholder $createPlaceholder): JsonResponse
    {
        $attributes = $request->safe()->only(['name', 'contact_type', 'contact_value']);
        $attributes['contact_type'] = ContactType::from($attributes['contact_type']);
        $placeholder = $createPlaceholder->execute($request->user(), $attributes);

        return PlaceholderResource::make($placeholder)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(
        UpdatePlaceholderRequest $request,
        Placeholder $placeholder,
        UpdatePlaceholder $updatePlaceholder,
    ): PlaceholderResource {
        $attributes = $request->safe()->only(['name', 'contact_type', 'contact_value']);

        if (isset($attributes['contact_type'])) {
            $attributes['contact_type'] = ContactType::from($attributes['contact_type']);
        }

        return PlaceholderResource::make(
            $updatePlaceholder->execute($request->user(), $placeholder, $attributes),
        );
    }
}
