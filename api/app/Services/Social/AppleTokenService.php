<?php

namespace App\Services\Social;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AppleTokenService
{
    public function exchange(string $code, string $nonce, VerifiedSocialIdentity $identity): array
    {
        $clientId = (string) config('ovezi.social.apple_client_id');
        $response = Http::asForm()->connectTimeout(5)->timeout(15)->post('https://appleid.apple.com/auth/token', [
            'client_id' => $clientId, 'client_secret' => $this->clientSecret($clientId),
            'code' => $code, 'grant_type' => 'authorization_code',
        ]);
        if (! $response->successful()) {
            throw ValidationException::withMessages(['authorization_code' => 'Apple could not confirm this sign-in. Please try again.']);
        }
        $verified = app(AppleIdentityTokenVerifier::class)->verify((string) $response->json('id_token'), $nonce, null);
        if ($verified->providerUserId !== $identity->providerUserId || ! is_string($response->json('refresh_token'))) {
            throw ValidationException::withMessages(['authorization_code' => 'Apple returned a different or incomplete account identity.']);
        }

        return ['refresh_token' => $response->json('refresh_token'), 'client_id' => $clientId];
    }

    public function revoke(string $token, string $clientId): void
    {
        $response = Http::asForm()->connectTimeout(5)->timeout(15)->post('https://appleid.apple.com/auth/revoke', [
            'client_id' => $clientId, 'client_secret' => $this->clientSecret($clientId),
            'token' => $token, 'token_type_hint' => 'refresh_token',
        ]);
        // Never include Apple's response or credentials in exceptions sent to monitoring.
        if (! $response->successful()) {
            throw new RuntimeException('Apple token revocation is temporarily unavailable.');
        }
    }

    private function clientSecret(string $clientId): string
    {
        $team = config('ovezi.social.apple_team_id');
        $keyId = config('ovezi.social.apple_key_id');
        $key = str_replace('\\n', "\n", (string) config('ovezi.social.apple_private_key'));
        if (! $team || ! $keyId || ! $key || ! $clientId) {
            throw new RuntimeException('Apple server credentials are not configured.');
        }

        return JWT::encode(['iss' => $team, 'iat' => time(), 'exp' => time() + 300,
            'aud' => 'https://appleid.apple.com', 'sub' => $clientId], $key, 'ES256', $keyId);
    }
}
