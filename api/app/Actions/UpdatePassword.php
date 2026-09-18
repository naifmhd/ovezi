<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UpdatePassword
{
    public function execute(User $user, string $currentPassword, string $newPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        DB::transaction(function () use ($user, $newPassword): void {
            $currentTokenId = $user->currentAccessToken()?->getKey();
            $user->update(['password' => $newPassword]);

            $otherTokens = $user->tokens();

            if ($currentTokenId !== null) {
                $otherTokens->whereKeyNot($currentTokenId);
            }

            $otherTokens->delete();
        });
    }
}
