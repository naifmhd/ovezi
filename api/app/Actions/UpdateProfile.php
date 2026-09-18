<?php

namespace App\Actions;

use App\Models\User;

class UpdateProfile
{
    /** @param array{name?: string, default_currency_code?: string} $attributes */
    public function execute(User $user, array $attributes): User
    {
        $user->update($attributes);

        return $user->refresh();
    }
}
