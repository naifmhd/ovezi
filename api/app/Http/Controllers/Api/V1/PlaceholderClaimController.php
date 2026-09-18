<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ClaimPlaceholder;
use App\ContactType;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PlaceholderClaimResource;
use App\Models\Placeholder;
use App\Services\PlaceholderContactNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class PlaceholderClaimController extends Controller
{
    public function index(
        Request $request,
        PlaceholderContactNormalizer $contactNormalizer,
    ): AnonymousResourceCollection {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => 'Verify your email before searching for placeholder history.',
            ]);
        }

        $contactHash = $contactNormalizer->hash(
            $contactNormalizer->normalize(ContactType::Email, $user->email),
        );
        $matches = Placeholder::query()
            ->where('contact_type', ContactType::Email)
            ->where('contact_hash', $contactHash)
            ->whereNull('claimed_by')
            ->with([
                'creator:id,name',
                'groupMemberships.group:id,name',
            ])
            ->withCount(['expenseSplits', 'groupMemberships'])
            ->oldest('id')
            ->get();

        return PlaceholderClaimResource::collection($matches);
    }

    public function store(
        Request $request,
        Placeholder $placeholder,
        ClaimPlaceholder $claimPlaceholder,
    ): PlaceholderClaimResource {
        return PlaceholderClaimResource::make(
            $claimPlaceholder->execute($request->user(), $placeholder),
        );
    }
}
