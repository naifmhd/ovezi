<?php

namespace App\Actions;

use App\GroupMemberRole;
use App\Models\ActivityLog;
use App\Models\Group;
use App\Models\GroupInvite;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AcceptGroupInvite
{
    public function execute(User $user, string $token): GroupMember
    {
        return DB::transaction(function () use ($user, $token): GroupMember {
            $invite = GroupInvite::query()
                ->where('token_hash', hash('sha256', $token))
                ->lockForUpdate()
                ->first();

            if ($invite === null) {
                throw new NotFoundHttpException;
            }

            if ($invite->revoked_at !== null) {
                throw ValidationException::withMessages(['invite' => 'This invite has been revoked.']);
            }

            if ($invite->accepted_at !== null) {
                throw ValidationException::withMessages(['invite' => 'This invite has already been accepted.']);
            }

            if ($invite->expires_at->isPast()) {
                throw ValidationException::withMessages(['invite' => 'This invite has expired.']);
            }

            if ($invite->invited_email !== null
                && ($user->email_verified_at === null
                    || mb_strtolower($user->email) !== mb_strtolower($invite->invited_email))) {
                throw ValidationException::withMessages([
                    'invite' => 'This invite requires the matching verified email address.',
                ]);
            }

            $group = Group::query()->lockForUpdate()->findOrFail($invite->group_id);

            if ($group->archived_at !== null) {
                throw ValidationException::withMessages([
                    'invite' => 'This group is archived and cannot accept new members.',
                ]);
            }

            $membership = $group->members()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            $event = 'member.joined';

            if ($membership === null) {
                $membership = $group->members()->create([
                    'user_id' => $user->id,
                    'role' => GroupMemberRole::Member,
                    'joined_at' => now(),
                ]);
            } elseif ($membership->left_at !== null) {
                $membership->update(['left_at' => null]);
                $event = 'member.rejoined';
            } else {
                $event = 'invite.accepted';
            }

            $invite->update([
                'accepted_by' => $user->id,
                'accepted_at' => now(),
            ]);

            ActivityLog::query()->create([
                'group_id' => $group->id,
                'actor_id' => $user->id,
                'subject_type' => $membership->getMorphClass(),
                'subject_id' => $membership->id,
                'event' => $event,
                'metadata' => ['invite_id' => $invite->id],
            ]);

            return $membership->load(['user:id,name', 'placeholder:id,name']);
        });
    }
}
