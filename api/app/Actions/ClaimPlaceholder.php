<?php

namespace App\Actions;

use App\ContactType;
use App\GroupMemberRole;
use App\Models\ActivityLog;
use App\Models\GroupMember;
use App\Models\Placeholder;
use App\Models\User;
use App\Services\PlaceholderContactNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClaimPlaceholder
{
    public function __construct(private readonly PlaceholderContactNormalizer $contactNormalizer) {}

    public function execute(User $claimant, Placeholder $placeholder): Placeholder
    {
        if (! $claimant->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'email' => 'Verify your email before claiming placeholder history.',
            ]);
        }

        $emailHash = $this->contactNormalizer->hash(
            $this->contactNormalizer->normalize(ContactType::Email, $claimant->email),
        );

        return DB::transaction(function () use ($claimant, $placeholder, $emailHash): Placeholder {
            $lockedPlaceholder = Placeholder::query()
                ->lockForUpdate()
                ->findOrFail($placeholder->id);

            if ($lockedPlaceholder->claimed_by !== null) {
                throw ValidationException::withMessages([
                    'placeholder' => 'This placeholder has already been claimed.',
                ]);
            }

            if ($lockedPlaceholder->contact_type !== ContactType::Email
                || ! hash_equals($lockedPlaceholder->contact_hash, $emailHash)) {
                abort(404);
            }

            $lockedPlaceholder->update([
                'claimed_by' => $claimant->id,
                'claimed_at' => now(),
            ]);

            $memberships = $lockedPlaceholder->groupMemberships()
                ->whereNull('left_at')
                ->lockForUpdate()
                ->get();

            foreach ($memberships as $placeholderMembership) {
                $userMembership = GroupMember::query()
                    ->where('group_id', $placeholderMembership->group_id)
                    ->where('user_id', $claimant->id)
                    ->lockForUpdate()
                    ->first();

                if ($userMembership === null) {
                    $userMembership = GroupMember::query()->create([
                        'group_id' => $placeholderMembership->group_id,
                        'user_id' => $claimant->id,
                        'role' => GroupMemberRole::Member,
                        'joined_at' => now(),
                    ]);
                } elseif ($userMembership->left_at !== null) {
                    $userMembership->update(['left_at' => null]);
                }

                $placeholderMembership->update(['left_at' => now()]);

                ActivityLog::query()->create([
                    'group_id' => $placeholderMembership->group_id,
                    'actor_id' => $claimant->id,
                    'subject_type' => $lockedPlaceholder->getMorphClass(),
                    'subject_id' => $lockedPlaceholder->id,
                    'event' => 'placeholder.claimed',
                    'metadata' => [
                        'claimed_by' => $claimant->id,
                        'user_membership_id' => $userMembership->id,
                    ],
                ]);
            }

            return $lockedPlaceholder->load([
                'creator:id,name',
                'groupMemberships.group:id,name',
            ])->loadCount(['expenseSplits', 'groupMemberships']);
        });
    }
}
