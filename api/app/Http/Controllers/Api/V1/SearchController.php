<?php

namespace App\Http\Controllers\Api\V1;

use App\ExpenseType;
use App\FriendshipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SearchRequest;
use App\Http\Resources\Api\V1\ExpenseResource;
use App\Http\Resources\Api\V1\GroupResource;
use App\Models\Expense;
use App\Models\Friendship;
use App\Models\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __invoke(SearchRequest $request): JsonResponse
    {
        $user = $request->user();
        $term = '%'.$request->string('q')->toString().'%';
        $groups = Group::query()
            ->whereHas('members', fn (Builder $query): Builder => $query
                ->whereBelongsTo($user)
                ->whereNull('left_at'))
            ->where('name', 'like', $term)
            ->withCount('activeMembers')
            ->latest('updated_at')
            ->limit(10)
            ->get();
        $expenses = Expense::query()
            ->where(function (Builder $query) use ($user): void {
                $query->where(function (Builder $groupQuery) use ($user): void {
                    $groupQuery->where('expense_type', ExpenseType::Group)
                        ->whereHas('group.members', fn (Builder $memberQuery): Builder => $memberQuery
                            ->whereBelongsTo($user)
                            ->whereNull('left_at'));
                })->orWhere(function (Builder $personalQuery) use ($user): void {
                    $personalQuery->where('expense_type', ExpenseType::Personal)
                        ->where('created_by', $user->id);
                })->orWhere(function (Builder $directQuery) use ($user): void {
                    $directQuery->where('expense_type', ExpenseType::Direct)
                        ->where(function (Builder $participantQuery) use ($user): void {
                            $participantQuery->where('created_by', $user->id)
                                ->orWhere('payer_user_id', $user->id)
                                ->orWhereHas('payerPlaceholder', fn (Builder $placeholderQuery): Builder => $placeholderQuery
                                    ->where('claimed_by', $user->id))
                                ->orWhereHas('splits', fn (Builder $splitQuery): Builder => $splitQuery
                                    ->where('user_id', $user->id)
                                    ->orWhereHas('placeholder', fn (Builder $placeholderQuery): Builder => $placeholderQuery
                                        ->where('claimed_by', $user->id)));
                        });
                });
            })
            ->where(function (Builder $query) use ($term): void {
                $query->where('description', 'like', $term)
                    ->orWhere('category', 'like', $term);
            })
            ->with([
                'payerUser:id,name',
                'payerPlaceholder:id,name,claimed_by',
                'payerPlaceholder.claimedBy:id,name',
                'splits.user:id,name',
                'splits.placeholder:id,name,claimed_by',
                'splits.placeholder.claimedBy:id,name',
            ])
            ->latest('occurred_at')
            ->limit(10)
            ->get();
        $friendships = Friendship::query()
            ->where('status', FriendshipStatus::Accepted)
            ->where(fn (Builder $query): Builder => $query
                ->where('user_id', $user->id)
                ->orWhere('friend_id', $user->id))
            ->where(function (Builder $query) use ($user, $term): void {
                $query->where(function (Builder $userQuery) use ($user, $term): void {
                    $userQuery->where('user_id', '!=', $user->id)
                        ->whereHas('user', fn (Builder $friendQuery): Builder => $friendQuery
                            ->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term));
                })->orWhere(function (Builder $friendQuery) use ($user, $term): void {
                    $friendQuery->where('friend_id', '!=', $user->id)
                        ->whereHas('friend', fn (Builder $userQuery): Builder => $userQuery
                            ->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term));
                });
            })
            ->with(['user:id,name,email', 'friend:id,name,email'])
            ->limit(10)
            ->get()
            ->map(function (Friendship $friendship) use ($user): array {
                $friend = $friendship->user_id === $user->id
                    ? $friendship->friend
                    : $friendship->user;

                return [
                    'id' => $friend->id,
                    'name' => $friend->name,
                    'email' => $friend->email,
                ];
            });

        return response()->json(['data' => [
            'groups' => GroupResource::collection($groups)->resolve($request),
            'expenses' => ExpenseResource::collection($expenses)->resolve($request),
            'friends' => $friendships,
        ]]);
    }
}
