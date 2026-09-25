<?php

namespace App\Services\Social;

use App\ConnectedAccountProvider;

class VerifiedSocialIdentity
{
    public function __construct(
        public readonly ConnectedAccountProvider $provider,
        public readonly string $providerUserId,
        public readonly ?string $email,
        public readonly bool $emailVerified,
        public readonly ?string $name,
        public readonly ?string $avatarUrl,
        public readonly ?int $issuedAt = null,
    ) {}
}
