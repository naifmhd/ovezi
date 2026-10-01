<?php

namespace App\Actions;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\VerifiedSocialIdentity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ConnectSocialAccount
{
    /** @param array{refresh_token: string, client_id: string}|null $appleTokens */
    public function execute(User $user, VerifiedSocialIdentity $identity, ?string $currentPassword, ?array $appleTokens): User
    {
        try {
            return DB::transaction(function () use ($user, $identity, $currentPassword, $appleTokens): User {
                $user = User::query()->lockForUpdate()->findOrFail($user->id);

                // Recheck against the locked account in case the password changed during provider verification.
                if ($user->password !== null && ! Hash::check($currentPassword ?? '', $user->password)) {
                    throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
                }

                $account = SocialAccount::query()
                    ->where('provider', $identity->provider->value)
                    ->where('provider_user_id', $identity->providerUserId)
                    ->lockForUpdate()->first();

                if ($account !== null && $account->user_id !== $user->id) {
                    throw ValidationException::withMessages(['provider' => 'This provider account is already connected to another Ovezi account.']);
                }

                if ($account === null && $user->socialAccounts()->where('provider', $identity->provider->value)->exists()) {
                    throw ValidationException::withMessages(['provider' => 'Disconnect your existing account for this provider before connecting a different one.']);
                }

                $account ??= new SocialAccount([
                    'user_id' => $user->id,
                    'provider' => $identity->provider,
                    'provider_user_id' => $identity->providerUserId,
                ]);
                $account->fill([
                    'provider_email' => $identity->email,
                    'provider_email_verified_at' => $identity->email !== null && $identity->emailVerified ? now() : null,
                    'avatar_url' => $identity->avatarUrl,
                ]);
                if ($appleTokens !== null) {
                    $account->fill($appleTokens);
                }
                $account->save();

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            // Unique provider/subject and user/provider indexes arbitrate concurrent connection attempts.
            throw ValidationException::withMessages(['provider' => 'This provider was connected by another request. Refresh your connected accounts and try again.']);
        }
    }
}
