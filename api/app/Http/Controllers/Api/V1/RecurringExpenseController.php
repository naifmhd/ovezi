<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateRecurringExpense;
use App\Actions\FindDuplicateExpense;
use App\Actions\UpdateRecurringExpense;
use App\ExpenseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreRecurringExpenseRequest;
use App\Http\Requests\Api\V1\UpdateRecurringExpenseRequest;
use App\Http\Resources\Api\V1\ExpenseResource;
use App\Http\Resources\Api\V1\RecurringExpenseResource;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\Group;
use App\Models\RecurringExpense;
use App\RecurrenceFrequency;
use App\Services\RecurrenceSchedule;
use App\SplitType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class RecurringExpenseController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', RecurringExpense::class);
        $user = request()->user();
        $recurringExpenses = RecurringExpense::query()
            ->where(function (Builder $query) use ($user): void {
                $query->where('created_by', $user->id)
                    ->orWhere(function (Builder $groupQuery) use ($user): void {
                        $groupQuery->where('expense_type', ExpenseType::Group)
                            ->whereHas('group.members', fn (Builder $memberQuery): Builder => $memberQuery
                                ->whereBelongsTo($user)
                                ->whereNull('left_at'));
                    })->orWhere(function (Builder $directQuery) use ($user): void {
                        $directQuery->where('expense_type', ExpenseType::Direct)
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
            ->with($this->relations())
            ->latest('updated_at')
            ->paginate(100);

        return RecurringExpenseResource::collection($recurringExpenses);
    }

    public function store(
        StoreRecurringExpenseRequest $request,
        CreateRecurringExpense $createRecurringExpense,
        FindDuplicateExpense $findDuplicateExpense,
    ): JsonResponse {
        $validated = $request->validated();
        $participants = $this->participants($validated['participants'] ?? []);
        $expenseType = ExpenseType::from($validated['expense_type']);
        $group = isset($validated['group_id'])
            ? Group::query()->findOrFail((int) $validated['group_id'])
            : null;
        $payerUserId = isset($validated['payer_user_id']) ? (int) $validated['payer_user_id'] : null;
        $payerPlaceholderId = isset($validated['payer_placeholder_id'])
            ? (int) $validated['payer_placeholder_id']
            : null;
        $occurredAt = CarbonImmutable::parse($validated['occurred_at']);

        if (! ($validated['confirmed_duplicate'] ?? false)) {
            $duplicate = $findDuplicateExpense->execute(
                $request->user(),
                $expenseType,
                $group,
                $payerUserId,
                $payerPlaceholderId,
                (int) $validated['amount_minor'],
                $validated['currency_code'],
                $validated['description'],
                $occurredAt,
                $participants,
            );

            if ($duplicate !== null) {
                return response()->json([
                    'message' => 'This looks like an expense you already added.',
                    'errors' => [
                        'duplicate' => ['An expense with the same details already exists for this date.'],
                    ],
                ], Response::HTTP_CONFLICT);
            }
        }

        $created = $createRecurringExpense->execute(
            $request->user(),
            [
                'expense_type' => $expenseType,
                'group' => $group,
                'payer_user_id' => $payerUserId,
                'payer_placeholder_id' => $payerPlaceholderId,
                'amount_minor' => (int) $validated['amount_minor'],
                'currency_code' => $validated['currency_code'],
                'description' => $validated['description'],
                'category' => $validated['category'] ?? null,
                'occurred_at' => $occurredAt,
                'split_type' => isset($validated['split_type'])
                    ? SplitType::from($validated['split_type'])
                    : SplitType::Equal,
                'participants' => $participants,
                'expense_rate' => $validated['expense_rate'] ?? null,
            ],
            RecurrenceFrequency::from($validated['frequency']),
            isset($validated['ends_on']) ? CarbonImmutable::parse($validated['ends_on']) : null,
        );

        return response()->json(['data' => [
            'expense' => ExpenseResource::make($this->loadExpense($created['expense']))->resolve($request),
            'recurring_expense' => RecurringExpenseResource::make(
                $this->loadRecurringExpense($created['recurring_expense']),
            )->resolve($request),
        ]], Response::HTTP_CREATED);
    }

    public function show(RecurringExpense $recurringExpense): RecurringExpenseResource
    {
        Gate::authorize('view', $recurringExpense);

        return RecurringExpenseResource::make($this->loadRecurringExpense($recurringExpense));
    }

    public function update(
        UpdateRecurringExpenseRequest $request,
        RecurringExpense $recurringExpense,
        UpdateRecurringExpense $updateRecurringExpense,
    ): RecurringExpenseResource {
        $validated = $request->validated();
        $updatedRecurringExpense = $updateRecurringExpense->execute(
            $request->user(),
            $recurringExpense,
            [
                'payer_user_id' => isset($validated['payer_user_id']) ? (int) $validated['payer_user_id'] : null,
                'payer_placeholder_id' => isset($validated['payer_placeholder_id'])
                    ? (int) $validated['payer_placeholder_id']
                    : null,
                'amount_minor' => (int) $validated['amount_minor'],
                'currency_code' => $validated['currency_code'],
                'description' => $validated['description'],
                'category' => $validated['category'] ?? null,
                'split_type' => isset($validated['split_type'])
                    ? SplitType::from($validated['split_type'])
                    : null,
                'participants' => $this->participants($validated['participants'] ?? []),
                'frequency' => RecurrenceFrequency::from($validated['frequency']),
                'ends_on' => isset($validated['ends_on']) ? CarbonImmutable::parse($validated['ends_on']) : null,
            ],
        );

        return RecurringExpenseResource::make($this->loadRecurringExpense($updatedRecurringExpense));
    }

    public function pause(RecurringExpense $recurringExpense): RecurringExpenseResource
    {
        Gate::authorize('update', $recurringExpense);
        $this->ensureActive($recurringExpense);
        $recurringExpense->update(['paused_at' => now()]);
        $this->logStatus($recurringExpense, 'recurring_expense.paused');

        return RecurringExpenseResource::make($this->loadRecurringExpense($recurringExpense));
    }

    public function resume(
        RecurringExpense $recurringExpense,
        RecurrenceSchedule $recurrenceSchedule,
    ): RecurringExpenseResource {
        Gate::authorize('update', $recurringExpense);

        if ($recurringExpense->canceled_at !== null) {
            throw ValidationException::withMessages(['recurring_expense' => 'A canceled schedule cannot be resumed.']);
        }

        $nextOccurrence = $recurrenceSchedule->firstAfter(
            CarbonImmutable::instance($recurringExpense->start_on),
            CarbonImmutable::today(),
            $recurringExpense->frequency,
        );

        if ($recurringExpense->ends_on !== null && $nextOccurrence->greaterThan($recurringExpense->ends_on)) {
            throw ValidationException::withMessages(['recurring_expense' => 'This schedule has already reached its end date.']);
        }

        $recurringExpense->update([
            'paused_at' => null,
            'next_occurrence_on' => $nextOccurrence,
        ]);
        $this->logStatus($recurringExpense, 'recurring_expense.resumed');

        return RecurringExpenseResource::make($this->loadRecurringExpense($recurringExpense));
    }

    public function destroy(RecurringExpense $recurringExpense): Response
    {
        Gate::authorize('delete', $recurringExpense);

        if ($recurringExpense->canceled_at === null) {
            $recurringExpense->update([
                'paused_at' => null,
                'canceled_at' => now(),
                'next_occurrence_on' => null,
            ]);
            $this->logStatus($recurringExpense, 'recurring_expense.canceled');
        }

        return response()->noContent();
    }

    /** @param list<array<string, mixed>> $participants */
    private function participants(array $participants): array
    {
        return array_map(fn (array $participant): array => [
            'user_id' => isset($participant['user_id']) ? (int) $participant['user_id'] : null,
            'placeholder_id' => isset($participant['placeholder_id']) ? (int) $participant['placeholder_id'] : null,
            ...(array_key_exists('value', $participant) ? ['value' => (int) $participant['value']] : []),
        ], $participants);
    }

    private function ensureActive(RecurringExpense $recurringExpense): void
    {
        if ($recurringExpense->canceled_at !== null || $recurringExpense->next_occurrence_on === null) {
            throw ValidationException::withMessages(['recurring_expense' => 'Only an active schedule can be paused.']);
        }
    }

    private function logStatus(RecurringExpense $recurringExpense, string $event): void
    {
        ActivityLog::query()->create([
            'group_id' => $recurringExpense->group_id,
            'actor_id' => request()->user()->id,
            'subject_type' => $recurringExpense->getMorphClass(),
            'subject_id' => $recurringExpense->id,
            'event' => $event,
        ]);
    }

    private function loadRecurringExpense(RecurringExpense $recurringExpense): RecurringExpense
    {
        return $recurringExpense->load($this->relations());
    }

    private function loadExpense(Expense $expense): Expense
    {
        return $expense->load([
            'payerUser:id,name',
            'payerPlaceholder:id,name,claimed_by',
            'payerPlaceholder.claimedBy:id,name',
            'splits.user:id,name',
            'splits.placeholder:id,name,claimed_by',
            'splits.placeholder.claimedBy:id,name',
        ]);
    }

    /** @return list<string> */
    private function relations(): array
    {
        return [
            'payerUser:id,name',
            'payerPlaceholder:id,name,claimed_by',
            'payerPlaceholder.claimedBy:id,name',
            'splits.user:id,name',
            'splits.placeholder:id,name,claimed_by',
            'splits.placeholder.claimedBy:id,name',
        ];
    }
}
