<?php

namespace App\Actions;

use App\ConnectedAccountProvider;
use App\Models\User;
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

        $socialAccount->delete();
    }
}
