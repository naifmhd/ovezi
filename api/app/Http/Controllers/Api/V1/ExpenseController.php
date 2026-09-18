<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateExpense;
use App\ExpenseType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreExpenseRequest;
use App\Http\Resources\Api\V1\ExpenseResource;
use App\Models\Group;
use App\SplitType;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ExpenseController extends Controller
{
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

        return ExpenseResource::make($expense)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
