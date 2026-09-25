<?php

namespace App\Services\Social;

use App\ConnectedAccountProvider;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AppleIdentityTokenVerifier
{
    public function verify(string $idToken, string $nonce, ?string $name): VerifiedSocialIdentity
    {
        $clientIds = config('ovezi.social.apple_client_ids', []);

        if ($clientIds === []) {
            throw new RuntimeException('Apple sign-in is not configured.');
        }

        $keySet = Cache::remember('social.apple.jwks', now()->addHours(6), fn (): array => Http::timeout(5)
            ->acceptJson()
            ->get('https://appleid.apple.com/auth/keys')
            ->throw()
            ->json());
        $claims = JWT::decode($idToken, JWK::parseKeySet($keySet, 'RS256'));

        if (($claims->iss ?? null) !== 'https://appleid.apple.com'
            || ! in_array($claims->aud ?? null, $clientIds, true)
            || empty($claims->sub)) {
            throw new RuntimeException('The Apple identity token claims are invalid.');
        }

        $expectedNonce = hash('sha256', $nonce);

        if (! isset($claims->nonce) || ! hash_equals($expectedNonce, (string) $claims->nonce)) {
            throw new RuntimeException('The Apple identity token nonce is invalid.');
        }

        $email = isset($claims->email) && $claims->email !== ''
            ? mb_strtolower((string) $claims->email)
            : null;
        $emailVerified = filter_var($claims->email_verified ?? false, FILTER_VALIDATE_BOOL);

        return new VerifiedSocialIdentity(
            ConnectedAccountProvider::Apple,
            (string) $claims->sub,
            $email,
            $emailVerified,
            $name,
            null,
            isset($claims->iat) ? (int) $claims->iat : null,
        );
    }
}
