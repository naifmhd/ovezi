<?php

namespace App\Actions;

use App\Models\GroupInvite;
use Illuminate\Validation\ValidationException;

class RevokeGroupInvite
{
    public function execute(GroupInvite $invite): GroupInvite
    {
        if ($invite->accepted_at !== null) {
            throw ValidationException::withMessages([
                'invite' => 'An accepted invite cannot be revoked.',
            ]);
        }

        if ($invite->revoked_at === null) {
            $invite->update(['revoked_at' => now()]);
        }

        return $invite->refresh();
    }
}
