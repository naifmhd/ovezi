<?php

namespace App\Actions;

use App\ConnectedAccountProvider;
use App\Jobs\RevokeAppleToken;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DisconnectSocialAccount
{
    public function execute(User $user, ConnectedAccountProvider $provider): void
    {
        $socialAccount = $user->socialAccounts()->where('provider', $provider->value)->first();

        if ($socialAccount === null) {
            throw new NotFoundHttpException;
        }

        $hasPassword = $user->password !== null;
        $hasAnotherProvider = $user->socialAccounts()->whereKeyNot($socialAccount->id)->exists();

        if (! $hasPassword && ! $hasAnotherProvider) {
            throw ValidationException::withMessages([
                'provider' => 'Add another login method before disconnecting this provider.',
            ]);
        }

        DB::transaction(function () use ($socialAccount, $provider): void {
            if ($provider === ConnectedAccountProvider::Apple && $socialAccount->refresh_token) {
                $id = DB::table('apple_token_revocations')->insertGetId([
                    'token' => Crypt::encryptString($socialAccount->refresh_token),
                    'client_id' => $socialAccount->client_id, 'created_at' => now(),
                ]);
                RevokeAppleToken::dispatch($id)->afterCommit();
            }
            $socialAccount->delete();
        });
    }
}
