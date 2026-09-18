<?php

namespace App\Actions;

use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Social\VerifiedSocialIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginWithSocialIdentity
{
    /** @return array{user: User, token: string} */
    public function execute(VerifiedSocialIdentity $identity, string $deviceName): array
    {
        $user = DB::transaction(function () use ($identity): User {
            $socialAccount = SocialAccount::query()
                ->where('provider', $identity->provider->value)
                ->where('provider_user_id', $identity->providerUserId)
                ->lockForUpdate()
                ->first();

            if ($socialAccount !== null) {
                $user = User::withTrashed()->findOrFail($socialAccount->user_id);

                if ($user->trashed()) {
                    throw ValidationException::withMessages([
                        'id_token' => 'This account has been deleted.',
                    ]);
                }

                $socialAccount->update([
                    'provider_email' => $identity->email,
                    'provider_email_verified_at' => $identity->emailVerified ? now() : null,
                    'avatar_url' => $identity->avatarUrl,
                ]);

                return $user;
            }

            if (! $identity->emailVerified || $identity->email === null) {
                throw ValidationException::withMessages([
                    'id_token' => 'A verified provider email is required to create or link an account.',
                ]);
            }

            $user = User::withTrashed()->where('email', $identity->email)->lockForUpdate()->first();

            if ($user?->trashed()) {
                throw ValidationException::withMessages(['id_token' => 'This account has been deleted.']);
            }

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $identity->name ?: Str::of($identity->email)->before('@')->replace(['.', '_'], ' ')->title(),
                    'email' => $identity->email,
                    'password' => null,
                    'avatar_path' => $identity->avatarUrl,
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();
            } else {
                $existingProvider = $user->socialAccounts()
                    ->where('provider', $identity->provider->value)
                    ->exists();

                if ($existingProvider) {
                    throw ValidationException::withMessages([
                        'id_token' => 'This provider is already connected to a different identity.',
                    ]);
                }

                if (! $user->hasVerifiedEmail()) {
                    $user->markEmailAsVerified();
                }
            }

            $user->socialAccounts()->create([
                'provider' => $identity->provider,
                'provider_user_id' => $identity->providerUserId,
                'provider_email' => $identity->email,
                'provider_email_verified_at' => now(),
                'avatar_url' => $identity->avatarUrl,
            ]);

            return $user;
        });
        $token = $user->createToken($deviceName);

        return ['user' => $user->refresh(), 'token' => $token->plainTextToken];
    }
}
