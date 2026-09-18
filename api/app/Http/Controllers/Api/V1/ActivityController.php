<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexActivityRequest;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\ActivityLog;
use App\Models\Expense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ActivityController extends Controller
{
    public function index(IndexActivityRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $activities = ActivityLog::query()
            ->where(function (Builder $query) use ($user): void {
                $query->where(function (Builder $personalQuery) use ($user): void {
                    $personalQuery->whereNull('group_id')
                        ->where(function (Builder $visibilityQuery) use ($user): void {
                            $visibilityQuery->where('actor_id', $user->id)
                                ->orWhereHasMorph(
                                    'subject',
                                    [Expense::class],
                                    fn (Builder $expenseQuery): Builder => $expenseQuery->whereHas(
                                        'splits',
                                        fn (Builder $splitQuery): Builder => $splitQuery->where('user_id', $user->id),
                                    ),
                                );
                        });
                })->orWhereHas('group.members', fn (Builder $memberQuery): Builder => $memberQuery
                    ->whereBelongsTo($user)
                    ->whereNull('left_at'));
            })
            ->with('actor:id,name')
            ->latest('created_at')
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return ActivityResource::collection($activities);
    }
}
