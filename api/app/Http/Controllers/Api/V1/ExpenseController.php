<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateExpense;
use App\Actions\UpdateExpense;
use App\ExpenseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexExpenseRequest;
use App\Http\Requests\Api\V1\StoreExpenseRequest;
use App\Http\Requests\Api\V1\UpdateExpenseRequest;
use App\Http\Resources\Api\V1\ExpenseResource;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\Group;
use App\SplitType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ExpenseController extends Controller
{
    public function index(IndexExpenseRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
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
            ->when($request->filled('group_id'), fn (Builder $query): Builder => $query
                ->where('group_id', $request->integer('group_id')))
            ->when($request->filled('expense_type'), fn (Builder $query): Builder => $query
                ->where('expense_type', $request->string('expense_type')->toString()))
            ->with([
                'payerUser:id,name',
                'payerPlaceholder:id,name,claimed_by',
                'payerPlaceholder.claimedBy:id,name',
                'splits.user:id,name',
                'splits.placeholder:id,name,claimed_by',
                'splits.placeholder.claimedBy:id,name',
            ])
            ->latest('occurred_at')
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return ExpenseResource::collection($expenses);
    }

    public function show(Expense $expense): ExpenseResource
    {
        Gate::authorize('view', $expense);

        return ExpenseResource::make($expense->load([
            'payerUser:id,name',
            'payerPlaceholder:id,name,claimed_by',
            'payerPlaceholder.claimedBy:id,name',
            'splits.user:id,name',
            'splits.placeholder:id,name,claimed_by',
            'splits.placeholder.claimedBy:id,name',
        ]));
    }

    public function store(StoreExpenseRequest $request, CreateExpense $createExpense): JsonResponse
    {
        $validated = $request->validated();
        $participants = array_map(fn (array $participant): array => [
            'user_id' => isset($participant['user_id']) ? (int) $participant['user_id'] : null,
            'placeholder_id' => isset($participant['placeholder_id']) ? (int) $participant['placeholder_id'] : null,
            ...(array_key_exists('value', $participant) ? ['value' => (int) $participant['value']] : []),
        ], $validated['participants'] ?? []);
        $expense = $createExpense->execute($request->user(), [
            'expense_type' => ExpenseType::from($validated['expense_type']),
            'group' => isset($validated['group_id'])
                ? Group::query()->findOrFail((int) $validated['group_id'])
                : null,
            'payer_user_id' => isset($validated['payer_user_id']) ? (int) $validated['payer_user_id'] : null,
            'payer_placeholder_id' => isset($validated['payer_placeholder_id'])
                ? (int) $validated['payer_placeholder_id']
                : null,
            'amount_minor' => (int) $validated['amount_minor'],
            'currency_code' => $validated['currency_code'],
            'description' => $validated['description'],
            'category' => $validated['category'] ?? null,
            'occurred_at' => CarbonImmutable::parse($validated['occurred_at']),
            'split_type' => isset($validated['split_type'])
                ? SplitType::from($validated['split_type'])
                : SplitType::Equal,
            'participants' => $participants,
            'expense_rate' => $validated['expense_rate'] ?? null,
        ]);

        return ExpenseResource::make($expense->load([
            'payerUser:id,name',
            'payerPlaceholder:id,name,claimed_by',
            'payerPlaceholder.claimedBy:id,name',
            'splits.user:id,name',
            'splits.placeholder:id,name,claimed_by',
            'splits.placeholder.claimedBy:id,name',
        ]))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense, UpdateExpense $updateExpense): ExpenseResource
    {
        $validated = $request->validated();
        $participants = array_map(fn (array $participant): array => [
            'user_id' => isset($participant['user_id']) ? (int) $participant['user_id'] : null,
            'placeholder_id' => isset($participant['placeholder_id']) ? (int) $participant['placeholder_id'] : null,
            ...(array_key_exists('value', $participant) ? ['value' => (int) $participant['value']] : []),
        ], $validated['participants'] ?? []);
        $updatedExpense = $updateExpense->execute($request->user(), $expense, [
            'payer_user_id' => isset($validated['payer_user_id']) ? (int) $validated['payer_user_id'] : null,
            'payer_placeholder_id' => isset($validated['payer_placeholder_id'])
                ? (int) $validated['payer_placeholder_id']
                : null,
            'amount_minor' => (int) $validated['amount_minor'],
            'currency_code' => $validated['currency_code'],
            'description' => $validated['description'],
            'category' => $validated['category'] ?? null,
            'occurred_at' => CarbonImmutable::parse($validated['occurred_at']),
            'split_type' => isset($validated['split_type'])
                ? SplitType::from($validated['split_type'])
                : SplitType::Equal,
            'participants' => $participants,
            'expense_rate' => $validated['expense_rate'] ?? null,
            'recalculate_rate' => (bool) ($validated['recalculate_rate'] ?? false),
        ]);

        return ExpenseResource::make($updatedExpense->load([
            'payerUser:id,name',
            'payerPlaceholder:id,name,claimed_by',
            'payerPlaceholder.claimedBy:id,name',
            'splits.user:id,name',
            'splits.placeholder:id,name,claimed_by',
            'splits.placeholder.claimedBy:id,name',
        ]));
    }

    public function destroy(Request $request, Expense $expense): Response
    {
        Gate::authorize('delete', $expense);

        DB::transaction(function () use ($expense, $request): void {
            ActivityLog::query()->create([
                'group_id' => $expense->group_id,
                'actor_id' => $request->user()->id,
                'subject_type' => $expense->getMorphClass(),
                'subject_id' => $expense->id,
                'event' => 'expense.deleted',
            ]);

            $expense->delete();
        });

        return response()->noContent();
    }

    public function restore(Request $request, int $expense): ExpenseResource
    {
        $expenseModel = Expense::withTrashed()->findOrFail($expense);
        Gate::authorize('restore', $expenseModel);

        if (! $expenseModel->trashed()) {
            throw ValidationException::withMessages([
                'expense' => 'This expense has not been deleted.',
            ]);
        }

        if ($expenseModel->deleted_at->lt(now()->subSeconds(30))) {
            throw ValidationException::withMessages([
                'expense' => 'The 30-second undo window has expired.',
            ]);
        }

        DB::transaction(function () use ($expenseModel, $request): void {
            $expenseModel->restore();

            ActivityLog::query()->create([
                'group_id' => $expenseModel->group_id,
                'actor_id' => $request->user()->id,
                'subject_type' => $expenseModel->getMorphClass(),
                'subject_id' => $expenseModel->id,
                'event' => 'expense.restored',
            ]);
        });

        return ExpenseResource::make($expenseModel->load([
            'payerUser:id,name',
            'payerPlaceholder:id,name',
            'splits.user:id,name',
            'splits.placeholder:id,name',
        ]));
    }
}
