<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\LoginWithSocialIdentity;
use App\ConnectedAccountProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\SocialLoginRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\Social\SocialIdentityVerifier;
use Illuminate\Http\JsonResponse;

class SocialLoginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        SocialLoginRequest $request,
        SocialIdentityVerifier $identityVerifier,
        LoginWithSocialIdentity $loginWithSocialIdentity,
    ): JsonResponse {
        $identity = $identityVerifier->verify(
            ConnectedAccountProvider::from($request->string('provider')->toString()),
            $request->string('id_token')->toString(),
            $request->filled('nonce') ? $request->string('nonce')->toString() : null,
            $request->filled('name') ? $request->string('name')->toString() : null,
        );
        $result = $loginWithSocialIdentity->execute(
            $identity,
            $request->string('device_name')->toString(),
        );

        return response()->json(['data' => [
            'user' => UserResource::make($result['user']),
            'token' => $result['token'],
        ]]);
    }
}
