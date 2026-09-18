<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\DisconnectSocialAccount;
use App\ConnectedAccountProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\DisconnectSocialAccountRequest;
use Illuminate\Http\Response;

class DisconnectSocialAccountController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        DisconnectSocialAccountRequest $request,
        string $provider,
        DisconnectSocialAccount $disconnectSocialAccount,
    ): Response {
        $disconnectSocialAccount->execute(
            $request->user(),
            ConnectedAccountProvider::from($provider),
        );

        return response()->noContent();
    }
}
