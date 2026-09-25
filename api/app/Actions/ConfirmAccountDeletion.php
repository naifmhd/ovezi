<?php

namespace App\Actions;

use App\ConnectedAccountProvider;
use App\Models\User;
use App\Services\Social\AppleTokenService;
use App\Services\Social\SocialIdentityVerifier;
use Illuminate\Validation\ValidationException;

class ConfirmAccountDeletion
{
    public function execute(User $user, array $input): void
    {
        if (! empty($input['provider'])) {
            $identity = app(SocialIdentityVerifier::class)->verify(ConnectedAccountProvider::from($input['provider']),
                $input['id_token'], $input['nonce'] ?? null, null);
            $account = $user->socialAccounts()->where('provider', $identity->provider->value)
                ->where('provider_user_id', $identity->providerUserId)->first();
            if (! $account || ! $identity->issuedAt || $identity->issuedAt < now()->subMinutes(5)->timestamp) {
                throw ValidationException::withMessages(['id_token' => 'Confirm using a fresh sign-in to this same connected account.']);
            }
            if ($identity->provider === ConnectedAccountProvider::Apple) {
                $account->update(app(AppleTokenService::class)->exchange($input['authorization_code'], $input['nonce'], $identity));
            }
            app(DeleteAccount::class)->deleteVerified($user);

            return;
        }
        app(DeleteAccount::class)->execute($user, $input['current_password'] ?? '');
    }
}
