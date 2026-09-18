<?php

namespace App\Services\Social;

use App\ConnectedAccountProvider;
use Google\Client;
use RuntimeException;

class GoogleIdentityTokenVerifier
{
    public function verify(string $idToken): VerifiedSocialIdentity
    {
        $clientIds = config('ovezi.social.google_client_ids', []);

        if ($clientIds === []) {
            throw new RuntimeException('Google sign-in is not configured.');
        }

        $claims = false;

        foreach ($clientIds as $clientId) {
            $claims = (new Client(['client_id' => $clientId]))->verifyIdToken($idToken);

            if ($claims !== false) {
                break;
            }
        }

        if ($claims === false || empty($claims['sub'])) {
            throw new RuntimeException('The Google identity token is invalid.');
        }

        $emailVerified = filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL);

        if (! $emailVerified || empty($claims['email'])) {
            throw new RuntimeException('Google did not provide a verified email address.');
        }

        return new VerifiedSocialIdentity(
            ConnectedAccountProvider::Google,
            (string) $claims['sub'],
            mb_strtolower((string) $claims['email']),
            true,
            isset($claims['name']) ? trim((string) $claims['name']) : null,
            isset($claims['picture']) ? (string) $claims['picture'] : null,
        );
    }
}
