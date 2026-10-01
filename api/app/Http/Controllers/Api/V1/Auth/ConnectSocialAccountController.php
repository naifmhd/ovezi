<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\ConnectSocialAccount;
use App\ConnectedAccountProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ConnectSocialAccountRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Social\AppleTokenService;
use App\Services\Social\SocialIdentityVerifier;
use Illuminate\Validation\ValidationException;

class ConnectSocialAccountController extends Controller
{
    public function __invoke(
        ConnectSocialAccountRequest $request,
        SocialIdentityVerifier $identityVerifier,
        AppleTokenService $appleTokenService,
        ConnectSocialAccount $connectSocialAccount,
    ): UserResource {
        $identity = $identityVerifier->verify(
            ConnectedAccountProvider::from($request->string('provider')->toString()),
            $request->string('id_token')->toString(),
            $request->filled('nonce') ? $request->string('nonce')->toString() : null,
            null,
        );

        if ($identity->issuedAt === null || $identity->issuedAt < now()->subMinutes(5)->timestamp
            || $identity->issuedAt > now()->addMinute()->timestamp) {
            throw ValidationException::withMessages(['id_token' => 'Please sign in with the provider again to connect your account.']);
        }

        $appleTokens = $identity->provider === ConnectedAccountProvider::Apple
            ? $appleTokenService->exchange($request->string('authorization_code')->toString(), $request->string('nonce')->toString(), $identity)
            : null;

        return UserResource::make($connectSocialAccount->execute(
            $request->user(),
            $identity,
            $request->filled('current_password') ? $request->string('current_password')->toString() : null,
            $appleTokens,
        ));
    }
}
