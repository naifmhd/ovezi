<?php

namespace App\Actions;

use App\ConnectedAccountProvider;
use App\ExpenseType;
use App\GroupMemberRole;
use App\Jobs\PurgeDeletedExpenses;
use App\Jobs\RevokeAppleToken;
use App\Models\Expense;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Placeholder;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeleteAccount
{
    public function execute(User $user, string $currentPassword): void
    {
        if (! $user->password || ! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }
        $this->deleteVerified($user);
    }

    /** Call only after password, provider, or single-use email confirmation. */
    public function deleteVerified(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $email = $user->email;
            foreach ($user->socialAccounts()->get() as $account) {
                if ($account->provider === ConnectedAccountProvider::Apple && $account->refresh_token) {
                    $id = DB::table('apple_token_revocations')->insertGetId([
                        'token' => Crypt::encryptString($account->refresh_token),
                        'client_id' => $account->client_id, 'created_at' => now(),
                    ]);
                    RevokeAppleToken::dispatch($id)->afterCommit();
                }
            }
            foreach ($user->groupMemberships()->where('role', GroupMemberRole::Owner->value)->whereNull('left_at')->orderBy('group_id')->get() as $membership) {
                $group = Group::query()->lockForUpdate()->find($membership->group_id);
                if ($group === null) {
                    continue;
                }
                $successor = GroupMember::query()->where('group_id', $group->id)->whereNull('left_at')
                    ->where('user_id', '!=', $user->id)->whereHas('user', fn ($query) => $query->whereNull('deleted_at'))->orderBy('id')->first();
                if ($successor) {
                    $successor->update(['role' => GroupMemberRole::Owner]);
                    $membership->update(['role' => GroupMemberRole::Member]);
                } else {
                    $group->update(['archived_at' => now()]);
                }
            }
            $personalSchedules = $user->createdRecurringExpenses()->where('expense_type', ExpenseType::Personal)->pluck('id');
            Expense::withTrashed()->whereIn('recurring_expense_id', $personalSchedules)->update(['recurring_expense_id' => null, 'recurring_occurrence_on' => null]);
            $user->createdRecurringExpenses()->whereIn('id', $personalSchedules)->delete();
            $user->createdRecurringExpenses()->update(['paused_at' => null, 'canceled_at' => now(), 'next_occurrence_on' => null]);
            $personalIds = Expense::withTrashed()->where('created_by', $user->id)->where('expense_type', ExpenseType::Personal)->pluck('id');
            DB::table('activity_logs')->where('subject_type', Expense::class)->whereIn('subject_id', $personalIds)->delete();
            Expense::withTrashed()->whereIn('id', $personalIds)->update(['deleted_at' => now()->subDays(31)]);
            // Contact details associated with the removed identity must not remain claimable.
            Placeholder::query()->where('claimed_by', $user->id)->each(function (Placeholder $placeholder): void {
                $placeholder->update(['name' => 'Deleted member', 'contact_value' => '', 'contact_hash' => hash('sha256', Str::uuid()->toString())]);
            });
            DB::table('group_invites')->where('invited_email', $email)->update(['invited_email' => null, 'revoked_at' => now()]);
            DB::table('group_invites')->where('invited_by', $user->id)->whereNull('accepted_at')->update(['revoked_at' => now()]);
            DB::table('friendships')->where('status', '!=', 'accepted')
                ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('friend_id', $user->id))->delete();
            DB::table('password_reset_tokens')->where('email', $email)->delete();
            DB::table('account_deletion_tokens')->where('user_id', $user->id)->delete();
            DB::table('financial_submissions')->where('user_id', $user->id)->delete();
            $user->socialAccounts()->delete();
            $user->pushTokens()->delete();
            $user->notificationPreference()->delete();
            $user->tokens()->delete();
            $user->forceFill(['name' => 'Deleted member', 'email' => 'deleted-'.$user->id.'-'.Str::uuid().'@deleted.invalid',
                'password' => null, 'remember_token' => null, 'avatar_path' => null, 'email_verified_at' => null])->save();
            $user->delete();
            PurgeDeletedExpenses::dispatch()->afterCommit();
        });
    }
}
