<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdateEmail
{
    public function execute(User $user, string $currentPassword, string $email): User
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        DB::transaction(function () use ($user, $email): void {
            $currentTokenId = $user->currentAccessToken()?->getKey();
            $user->forceFill([
                'email' => $email,
                'email_verified_at' => null,
            ])->save();
            $otherTokens = $user->tokens();

            if ($currentTokenId !== null) {
                $otherTokens->whereKeyNot($currentTokenId);
            }

            $otherTokens->delete();
        });

        $user->sendEmailVerificationNotification();

        return $user->refresh();
    }
}
