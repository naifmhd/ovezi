<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreLendingLoanRequest;
use App\Http\Requests\Api\V1\StoreQuickLendingLoanRequest;
use App\Http\Resources\Api\V1\LendingLoanResource;
use App\Models\LendingContact;
use App\Models\LendingRepayment;
use App\Services\LendingLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LendingLoanController extends Controller
{
    public function index(Request $request, int $contact, LendingLedger $ledger): AnonymousResourceCollection
    {
        $ledger->contact($request->user(), $contact);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);

        return LendingLoanResource::collection($this->query($request, $ledger)->where('lending_contact_id', $contact)->latest('occurred_on')->latest('id')->paginate(30));
    }

    public function store(StoreLendingLoanRequest $request, int $contact, LendingLedger $ledger): JsonResponse
    {
        return LendingLoanResource::make($ledger->createLoan($request->user(), $contact, $request->validated()))->response()->setStatusCode(201);
    }

    public function quickStore(StoreQuickLendingLoanRequest $request, LendingLedger $ledger): JsonResponse
    {
        return DB::transaction(function () use ($request, $ledger): JsonResponse {
            $data = $request->validated();
            $contactId = $data['contact_id'] ?? null;
            if ($contactId === null) {
                $email = empty($data['person']['email']) ? null : mb_strtolower(trim($data['person']['email']));
                $contactId = LendingContact::create([
                    'owner_id' => $request->user()->id, 'name' => trim($data['person']['name']),
                    'email' => $email, 'email_hash' => $email ? hash('sha256', $email) : null,
                ])->id;
            }
            unset($data['contact_id'], $data['person']);

            return LendingLoanResource::make($ledger->createLoan($request->user(), (int) $contactId, $data))->response()->setStatusCode(201);
        }, 3);
    }

    public function show(Request $request, int $loan, LendingLedger $ledger): LendingLoanResource
    {
        return LendingLoanResource::make($this->query($request, $ledger)->findOrFail($loan));
    }

    public function update(Request $request, int $loan, LendingLedger $ledger): LendingLoanResource
    {
        $data = $request->validate(['action' => ['required', Rule::in(['acknowledge', 'dispute', 'void', 'confirm_void', 'reject_void'])]]);

        return LendingLoanResource::make($ledger->changeLoan($request->user(), $loan, $data['action']));
    }

    public function history(Request $request, int $loan, LendingLedger $ledger): JsonResponse
    {
        $record = $ledger->loans($request->user())->findOrFail($loan);
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $events = $record->events()->with('actor:id,name')->latest('id')->paginate(30);
        $repayments = LendingRepayment::query()->whereIn('id', $events->pluck('details.repayment_id')->filter())->get()->keyBy('id');
        $events->through(fn ($event) => [
            'id' => $event->id, 'event' => $event->event, 'actor_name' => $event->actor?->name ?? 'Deleted member',
            'created_at' => $event->created_at, 'details' => $event->details,
            'repayment' => isset($event->details['repayment_id']) ? $repayments->get($event->details['repayment_id'])?->only(['id', 'amount_minor', 'currency_code', 'occurred_on', 'note', 'status', 'recorded_by', 'confirmed_by']) : null,
        ]);

        return response()->json($events);
    }

    private function query(Request $request, LendingLedger $ledger): Builder
    {
        return $ledger->loans($request->user())->with('contact')->addSelect(['pending_minor' => DB::table('lending_repayment_allocations')
            ->join('lending_repayments', 'lending_repayments.id', '=', 'lending_repayment_allocations.lending_repayment_id')
            ->whereColumn('lending_loan_id', 'lending_loans.id')->where('status', 'pending')->selectRaw('COALESCE(SUM(lending_repayment_allocations.amount_minor), 0)')]);
    }
}
