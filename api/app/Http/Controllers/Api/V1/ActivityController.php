<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexActivityRequest;
use App\Http\Resources\Api\V1\TimelineActivityResource;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\LendingEvent;
use App\Models\Settlement;
use App\Models\User;
use App\Services\LendingLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ActivityController extends Controller
{
    public function index(IndexActivityRequest $request, LendingLedger $ledger): AnonymousResourceCollection
    {
        $user = $request->user();
        $source = $request->input('source', 'all');
        $activities = $this->visibleActivities($user)
            ->when($source === 'groups', fn (Builder $query) => $query->whereNotNull('group_id'))
            ->when($source === 'personal', fn (Builder $query) => $query->whereNull('group_id'))
            ->when($source === 'lending', fn (Builder $query) => $query->whereRaw('1 = 0'));
        $lending = LendingEvent::query()->whereIn('lending_loan_id', $ledger->loans($user)->select('id'))
            ->when(! in_array($source, ['all', 'lending']), fn (Builder $query) => $query->whereRaw('1 = 0'));
        if ($request->filled('q')) {
            $term = '%'.$request->string('q')->trim()->toString().'%';
            $activities->where(fn (Builder $query) => $query->where('event', 'like', $term)
                ->orWhere('metadata->description', 'like', $term)
                ->orWhereHas('actor', fn (Builder $actor) => $actor->where('name', 'like', $term))
                ->orWhereHas('group', fn (Builder $group) => $group->where('name', 'like', $term)));
            $lending->where(fn (Builder $query) => $query->where('event', 'like', $term)
                ->orWhereHas('actor', fn (Builder $actor) => $actor->where('name', 'like', $term))
                ->orWhereHas('loan', fn (Builder $loan) => $loan->where('description', 'like', $term)
                    ->orWhereHas('contact', fn (Builder $contact) => $contact
                        ->where(fn (Builder $own) => $own->where('owner_id', $user->id)->where('name', 'like', $term))
                        ->orWhere(fn (Builder $shared) => $shared->where('owner_id', '!=', $user->id)->whereHas('owner', fn (Builder $owner) => $owner->where('name', 'like', $term))))));
        }
        foreach ([$activities, $lending] as $query) {
            $query->when($request->filled('after'), fn (Builder $query) => $query->where('created_at', '>=', Carbon::parse($request->input('after'))->utc()))
                ->when($request->filled('before'), fn (Builder $query) => $query->where('created_at', '<', Carbon::parse($request->input('before'))->utc()));
        }
        $union = (clone $activities)->select(['id', 'created_at'])->selectRaw("'activity' as source")->toBase()
            ->unionAll((clone $lending)->select(['id', 'created_at'])->selectRaw("'lending' as source")->toBase());
        $page = DB::query()->fromSub($union, 'timeline')->orderByDesc('created_at')->orderByDesc('id')->orderByDesc('source')
            ->paginate($request->integer('per_page', 20));
        $activityRecords = $activities->with(['actor:id,name', 'group:id,name'])
            ->whereIn('id', $page->getCollection()->where('source', 'activity')->pluck('id'))->get()->keyBy('id');
        $lendingRecords = $lending->with(['actor:id,name', 'loan.contact.owner:id,name'])
            ->whereIn('id', $page->getCollection()->where('source', 'lending')->pluck('id'))->get()->keyBy('id');
        $page->setCollection($page->getCollection()->map(fn ($entry) => $entry->source === 'activity'
            ? $activityRecords->get($entry->id) : $lendingRecords->get($entry->id))->filter()->values());

        return TimelineActivityResource::collection($page);
    }

    private function visibleActivities(User $user): Builder
    {
        return ActivityLog::query()
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
                                        fn (Builder $splitQuery): Builder => $splitQuery
                                            ->where('user_id', $user->id)
                                            ->orWhereHas('placeholder', fn (Builder $placeholderQuery): Builder => $placeholderQuery
                                                ->where('claimed_by', $user->id)),
                                    ),
                                )->orWhereHasMorph(
                                    'subject',
                                    [Settlement::class],
                                    fn (Builder $settlementQuery): Builder => $settlementQuery
                                        ->where('from_user_id', $user->id)
                                        ->orWhere('to_user_id', $user->id)
                                        ->orWhereHas('fromPlaceholder', fn (Builder $placeholderQuery): Builder => $placeholderQuery
                                            ->where('claimed_by', $user->id))
                                        ->orWhereHas('toPlaceholder', fn (Builder $placeholderQuery): Builder => $placeholderQuery
                                            ->where('claimed_by', $user->id)),
                                );
                        });
                })->orWhereHas('group.members', fn (Builder $memberQuery): Builder => $memberQuery
                    ->whereBelongsTo($user)
                    ->whereNull('left_at'));
            });
    }
}
