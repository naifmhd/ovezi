<?php

namespace App\Actions;

use App\GroupMemberRole;
use App\Models\User;
use App\Services\GroupBalanceCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class DeleteAccount
{
    public function __construct(private readonly GroupBalanceCalculator $balanceCalculator) {}

    public function execute(User $user, string $currentPassword): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'The current password is incorrect.',
            ]);
        }

        $ownedGroupCount = $user->groupMemberships()
            ->where('role', GroupMemberRole::Owner->value)
            ->whereNull('left_at')
            ->count();

        if ($ownedGroupCount > 0) {
            throw ValidationException::withMessages([
                'account' => 'Transfer ownership of every group before deleting your account.',
            ]);
        }

        $openBalanceExists = $user->groupMemberships()
            ->whereNull('left_at')
            ->with('group')
            ->get()
            ->contains(function ($membership) use ($user): bool {
                if ($membership->group === null) {
                    return false;
                }

                $balance = $this->balanceCalculator->calculate($membership->group)["user:{$user->id}"] ?? 0;

                return $balance !== 0;
            });

        if ($openBalanceExists) {
            throw ValidationException::withMessages([
                'account' => 'Settle every open group balance before deleting your account.',
            ]);
        }

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $user->delete();
        });
    }
}
