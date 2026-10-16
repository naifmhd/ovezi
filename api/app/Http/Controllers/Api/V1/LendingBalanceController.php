<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LendingLoanResource;
use App\Services\LendingLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LendingBalanceController extends Controller
{
    public function __invoke(Request $request, int $contact, LendingLedger $ledger): JsonResponse
    {
        $person = $ledger->contact($request->user(), $contact)->load('owner:id,name');
        $data = $request->validate([
            'currency_code' => ['required', 'string', Rule::exists('currencies', 'code')],
            'direction' => ['required', Rule::in(['lent', 'borrowed'])],
            'view' => ['sometimes', Rule::in(['loans', 'repayments'])],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $loans = $ledger->loans($request->user())->where('lending_contact_id', $contact)->whereNull('voided_at')
            ->where('currency_code', $data['currency_code'])
            ->where('direction', $ledger->direction($person, $request->user(), $data['direction']));
        $totals = (clone $loans)->selectRaw('COALESCE(SUM(amount_minor), 0) as principal, COALESCE(SUM(repaid_minor), 0) as repaid')->first();
        $summary = [
            'currency_code' => $data['currency_code'], 'direction' => $data['direction'],
            'amount_minor' => (int) $totals->principal, 'repaid_minor' => (int) $totals->repaid,
            'outstanding_minor' => (int) $totals->principal - (int) $totals->repaid,
        ];
        if (($data['view'] ?? 'loans') === 'repayments') {
            $page = DB::table('lending_repayment_allocations as allocations')
                ->join('lending_repayments as repayments', 'repayments.id', '=', 'allocations.lending_repayment_id')
                ->join('lending_loans as loans', 'loans.id', '=', 'allocations.lending_loan_id')
                ->whereIn('allocations.lending_loan_id', (clone $loans)->select('id'))->where('repayments.status', 'confirmed')
                ->select(['allocations.id', 'allocations.lending_loan_id as loan_id', 'repayments.id as repayment_id', 'repayments.occurred_on', 'allocations.amount_minor', 'loans.description'])
                ->orderByDesc('repayments.occurred_on')->orderByDesc('allocations.id')->paginate(30);
            $page->through(fn ($row) => [
                'id' => $row->id, 'loan_id' => $row->loan_id, 'repayment_id' => $row->repayment_id,
                'occurred_on' => substr($row->occurred_on, 0, 10), 'amount_minor' => (int) $row->amount_minor,
                'description' => $row->description,
            ]);
        } else {
            $page = $loans->with('contact')->latest('occurred_on')->latest('id')->paginate(30);
            $page->through(fn ($loan) => (new LendingLoanResource($loan))->resolve($request));
        }

        return response()->json([
            'data' => $page->items(),
            'summary' => $summary,
            'person_name' => $person->owner_id === $request->user()->id ? $person->name : ($person->owner?->name ?? 'Deleted member'),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
