<?php

namespace App\Services\Social;

use App\ConnectedAccountProvider;
use Illuminate\Validation\ValidationException;
use Throwable;

class SocialIdentityVerifier
{
    public function __construct(
        private readonly GoogleIdentityTokenVerifier $googleVerifier,
        private readonly AppleIdentityTokenVerifier $appleVerifier,
    ) {}

    public function verify(
        ConnectedAccountProvider $provider,
        string $idToken,
        ?string $nonce,
        ?string $name,
    ): VerifiedSocialIdentity {
        try {
            return match ($provider) {
                ConnectedAccountProvider::Google => $this->googleVerifier->verify($idToken),
                ConnectedAccountProvider::Apple => $this->appleVerifier->verify(
                    $idToken,
                    $nonce ?? throw new \InvalidArgumentException('Apple sign-in requires a nonce.'),
                    $name,
                ),
            };
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages([
                'id_token' => 'The social identity token could not be verified.',
            ]);
        }
    }
}
