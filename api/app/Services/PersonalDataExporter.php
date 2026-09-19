<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\Friendship;
use App\Models\GroupInvite;
use App\Models\GroupMember;
use App\Models\RecurringExpense;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class PersonalDataExporter
{
    /** @return array<string, mixed> */
    public function export(User $user): array
    {
        return [
            'export_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'default_currency_code' => $user->default_currency_code,
                'avatar_path' => $user->avatar_path,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
            'connected_accounts' => $user->socialAccounts()->oldest('id')->get()->map(fn ($account): array => [
                'provider' => $account->provider->value,
                'provider_user_id' => $account->provider_user_id,
                'provider_email' => $account->provider_email,
                'provider_email_verified_at' => $account->provider_email_verified_at?->toIso8601String(),
                'avatar_url' => $account->avatar_url,
                'connected_at' => $account->created_at?->toIso8601String(),
            ])->all(),
            'friendships' => $this->friendships($user),
            'group_memberships' => $this->groupMemberships($user),
            'created_placeholders' => $user->createdPlaceholders()->oldest('id')->get()->map(fn ($placeholder): array => [
                'id' => $placeholder->id,
                'name' => $placeholder->name,
                'contact_type' => $placeholder->contact_type->value,
                'contact_value' => $placeholder->contact_value,
                'claimed_by' => $placeholder->claimed_by,
                'claimed_at' => $placeholder->claimed_at?->toIso8601String(),
                'created_at' => $placeholder->created_at?->toIso8601String(),
            ])->all(),
            'expenses' => $this->expenses($user),
            'recurring_expenses' => $this->recurringExpenses($user),
            'settlements' => $this->settlements($user),
            'created_group_invitations' => $this->groupInvitations($user),
            'activity' => ActivityLog::query()
                ->where('actor_id', $user->id)
                ->oldest('id')
                ->get()
                ->map(fn (ActivityLog $activity): array => [
                    'id' => $activity->id,
                    'group_id' => $activity->group_id,
                    'subject_type' => $activity->subject_type,
                    'subject_id' => $activity->subject_id,
                    'event' => $activity->event,
                    'metadata' => $activity->metadata,
                    'created_at' => $activity->created_at?->toIso8601String(),
                ])->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function friendships(User $user): array
    {
        return Friendship::query()
            ->where(fn (Builder $query): Builder => $query
                ->where('user_id', $user->id)
                ->orWhere('friend_id', $user->id))
            ->with(['user:id,name,email', 'friend:id,name,email'])
            ->oldest('id')
            ->get()
            ->map(function (Friendship $friendship) use ($user): array {
                $friend = $friendship->user_id === $user->id ? $friendship->friend : $friendship->user;

                return [
                    'id' => $friendship->id,
                    'friend' => $friend ? [
                        'id' => $friend->id,
                        'name' => $friend->name,
                        'email' => $friend->email,
                    ] : null,
                    'status' => $friendship->status->value,
                    'direction' => $friendship->requested_by === $user->id ? 'outgoing' : 'incoming',
                    'accepted_at' => $friendship->accepted_at?->toIso8601String(),
                    'created_at' => $friendship->created_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function groupMemberships(User $user): array
    {
        return GroupMember::query()
            ->where('user_id', $user->id)
            ->with(['group' => fn ($query) => $query->withTrashed()])
            ->oldest('id')
            ->get()
            ->map(fn (GroupMember $membership): array => [
                'id' => $membership->id,
                'group' => $membership->group ? [
                    'id' => $membership->group->id,
                    'name' => $membership->group->name,
                    'reporting_currency_code' => $membership->group->reporting_currency_code,
                    'archived_at' => $membership->group->archived_at?->toIso8601String(),
                    'deleted_at' => $membership->group->deleted_at?->toIso8601String(),
                ] : null,
                'role' => $membership->role->value,
                'joined_at' => $membership->joined_at?->toIso8601String(),
                'left_at' => $membership->left_at?->toIso8601String(),
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function expenses(User $user): array
    {
        return Expense::query()
            ->withTrashed()
            ->where(function (Builder $query) use ($user): void {
                $query->where('created_by', $user->id)
                    ->orWhere('payer_user_id', $user->id)
                    ->orWhereHas('payerPlaceholder', fn (Builder $placeholder): Builder => $placeholder
                        ->where('claimed_by', $user->id))
                    ->orWhereHas('splits', fn (Builder $split): Builder => $split
                        ->where('user_id', $user->id)
                        ->orWhereHas('placeholder', fn (Builder $placeholder): Builder => $placeholder
                            ->where('claimed_by', $user->id)));
            })
            ->with(['splits.user:id,name', 'splits.placeholder:id,name'])
            ->oldest('id')
            ->get()
            ->map(fn (Expense $expense): array => [
                'id' => $expense->id,
                'recurring_expense_id' => $expense->recurring_expense_id,
                'recurring_occurrence_on' => $expense->recurring_occurrence_on?->toDateString(),
                'expense_type' => $expense->expense_type->value,
                'group_id' => $expense->group_id,
                'payer_user_id' => $expense->payer_user_id,
                'payer_placeholder_id' => $expense->payer_placeholder_id,
                'amount_minor' => $expense->amount_minor,
                'currency_code' => $expense->currency_code,
                'reporting_amount_minor' => $expense->reporting_amount_minor,
                'reporting_currency_code' => $expense->reporting_currency_code,
                'exchange_rate' => $expense->exchange_rate,
                'exchange_rate_source' => $expense->exchange_rate_source->value,
                'exchange_rate_effective_date' => $expense->exchange_rate_effective_date?->toDateString(),
                'description' => $expense->description,
                'category' => $expense->category,
                'receipt_image_path' => $expense->receipt_image_path,
                'occurred_at' => $expense->occurred_at->toIso8601String(),
                'created_by' => $expense->created_by,
                'deleted_at' => $expense->deleted_at?->toIso8601String(),
                'created_at' => $expense->created_at?->toIso8601String(),
                'updated_at' => $expense->updated_at?->toIso8601String(),
                'splits' => $expense->splits->map(fn ($split): array => [
                    'id' => $split->id,
                    'user_id' => $split->user_id,
                    'placeholder_id' => $split->placeholder_id,
                    'participant_name' => $split->user?->name ?? $split->placeholder?->name,
                    'amount_owed_minor' => $split->amount_owed_minor,
                    'reporting_amount_owed_minor' => $split->reporting_amount_owed_minor,
                    'split_type' => $split->split_type->value,
                    'split_value' => $split->split_value,
                ])->all(),
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function recurringExpenses(User $user): array
    {
        return RecurringExpense::query()
            ->where(function (Builder $query) use ($user): void {
                $query->where('created_by', $user->id)
                    ->orWhere(function (Builder $groupQuery) use ($user): void {
                        $groupQuery->where('expense_type', 'group')
                            ->whereHas('group.members', fn (Builder $memberQuery): Builder => $memberQuery
                                ->whereBelongsTo($user)
                                ->whereNull('left_at'));
                    })->orWhere(function (Builder $directQuery) use ($user): void {
                        $directQuery->where('expense_type', 'direct')
                            ->where(function (Builder $participantQuery) use ($user): void {
                                $participantQuery->where('payer_user_id', $user->id)
                                    ->orWhereHas('payerPlaceholder', fn (Builder $placeholderQuery): Builder => $placeholderQuery
                                        ->where('claimed_by', $user->id))
                                    ->orWhereHas('splits', fn (Builder $splitQuery): Builder => $splitQuery
                                        ->where('user_id', $user->id)
                                        ->orWhereHas('placeholder', fn (Builder $placeholderQuery): Builder => $placeholderQuery
                                            ->where('claimed_by', $user->id)));
                            });
                    });
            })
            ->with(['splits.user:id,name', 'splits.placeholder:id,name'])
            ->oldest('id')
            ->get()
            ->map(fn (RecurringExpense $recurringExpense): array => [
                'id' => $recurringExpense->id,
                'expense_type' => $recurringExpense->expense_type->value,
                'group_id' => $recurringExpense->group_id,
                'payer_user_id' => $recurringExpense->payer_user_id,
                'payer_placeholder_id' => $recurringExpense->payer_placeholder_id,
                'amount_minor' => $recurringExpense->amount_minor,
                'currency_code' => $recurringExpense->currency_code,
                'description' => $recurringExpense->description,
                'category' => $recurringExpense->category,
                'split_type' => $recurringExpense->split_type?->value,
                'frequency' => $recurringExpense->frequency->value,
                'start_on' => $recurringExpense->start_on->toDateString(),
                'next_occurrence_on' => $recurringExpense->next_occurrence_on?->toDateString(),
                'ends_on' => $recurringExpense->ends_on?->toDateString(),
                'paused_at' => $recurringExpense->paused_at?->toIso8601String(),
                'canceled_at' => $recurringExpense->canceled_at?->toIso8601String(),
                'created_by' => $recurringExpense->created_by,
                'created_at' => $recurringExpense->created_at?->toIso8601String(),
                'updated_at' => $recurringExpense->updated_at?->toIso8601String(),
                'splits' => $recurringExpense->splits->map(fn ($split): array => [
                    'id' => $split->id,
                    'user_id' => $split->user_id,
                    'placeholder_id' => $split->placeholder_id,
                    'participant_name' => $split->user?->name ?? $split->placeholder?->name,
                    'split_value' => $split->split_value,
                ])->all(),
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function settlements(User $user): array
    {
        return Settlement::query()
            ->withTrashed()
            ->where(function (Builder $query) use ($user): void {
                $query->where('created_by', $user->id)
                    ->orWhere('from_user_id', $user->id)
                    ->orWhere('to_user_id', $user->id)
                    ->orWhereHas('fromPlaceholder', fn (Builder $placeholder): Builder => $placeholder
                        ->where('claimed_by', $user->id))
                    ->orWhereHas('toPlaceholder', fn (Builder $placeholder): Builder => $placeholder
                        ->where('claimed_by', $user->id));
            })
            ->oldest('id')
            ->get()
            ->map(fn (Settlement $settlement): array => [
                'id' => $settlement->id,
                'group_id' => $settlement->group_id,
                'from_user_id' => $settlement->from_user_id,
                'from_placeholder_id' => $settlement->from_placeholder_id,
                'to_user_id' => $settlement->to_user_id,
                'to_placeholder_id' => $settlement->to_placeholder_id,
                'amount_minor' => $settlement->amount_minor,
                'currency_code' => $settlement->currency_code,
                'reporting_amount_minor' => $settlement->reporting_amount_minor,
                'reporting_currency_code' => $settlement->reporting_currency_code,
                'exchange_rate' => $settlement->exchange_rate,
                'exchange_rate_source' => $settlement->exchange_rate_source->value,
                'exchange_rate_effective_date' => $settlement->exchange_rate_effective_date?->toDateString(),
                'method' => $settlement->method,
                'note' => $settlement->note,
                'occurred_at' => $settlement->occurred_at->toIso8601String(),
                'created_by' => $settlement->created_by,
                'deleted_at' => $settlement->deleted_at?->toIso8601String(),
                'created_at' => $settlement->created_at?->toIso8601String(),
                'updated_at' => $settlement->updated_at?->toIso8601String(),
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function groupInvitations(User $user): array
    {
        return GroupInvite::query()
            ->where('invited_by', $user->id)
            ->with(['group' => fn ($query) => $query->withTrashed()])
            ->oldest('id')
            ->get()
            ->map(fn (GroupInvite $invite): array => [
                'id' => $invite->id,
                'group_id' => $invite->group_id,
                'group_name' => $invite->group?->name,
                'invited_email' => $invite->invited_email,
                'expires_at' => $invite->expires_at->toIso8601String(),
                'revoked_at' => $invite->revoked_at?->toIso8601String(),
                'accepted_by' => $invite->accepted_by,
                'accepted_at' => $invite->accepted_at?->toIso8601String(),
                'created_at' => $invite->created_at?->toIso8601String(),
            ])->all();
    }
}
