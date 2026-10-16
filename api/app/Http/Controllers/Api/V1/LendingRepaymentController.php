<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLendingRepaymentRequest;
use App\Services\LendingLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LendingRepaymentController extends Controller
{
    public function store(StoreLendingRepaymentRequest $request, int $contact, LendingLedger $ledger): JsonResponse
    {
        return response()->json(['data' => $ledger->repay($request->user(), $contact, $request->validated())], 201);
    }

    public function update(Request $request, int $repayment, LendingLedger $ledger): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['confirm', 'reject', 'reverse'])]]);
        $ledger->changeRepayment($request->user(), $repayment, $data['action']);

        return response()->json(['message' => 'Repayment updated.']);
    }
}
