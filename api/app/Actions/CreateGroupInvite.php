<?php

namespace App\Actions;

use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateGroupInvite
{
    private const EXPIRATION_DAYS = 7;

    /** @return array{invite: GroupInvite, token: string} */
    public function execute(User $inviter, Group $group, ?string $invitedEmail): array
    {
        return DB::transaction(function () use ($inviter, $group, $invitedEmail): array {
            $lockedGroup = Group::query()->lockForUpdate()->findOrFail($group->id);

            if ($lockedGroup->archived_at !== null) {
                throw ValidationException::withMessages([
                    'group' => 'Reopen the group before creating an invite.',
                ]);
            }

            $token = bin2hex(random_bytes(32));
            $invite = $lockedGroup->invites()->create([
                'invited_by' => $inviter->id,
                'token_hash' => hash('sha256', $token),
                'invited_email' => $invitedEmail,
                'expires_at' => now()->addDays(self::EXPIRATION_DAYS),
            ]);

            return ['invite' => $invite, 'token' => $token];
        });
    }
}
