<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreQuickLendingLoanRequest;
use App\Http\Resources\Api\V1\LendingLoanResource;
use App\Models\LendingContact;
use App\Services\LendingLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class LendingDuplicateController extends Controller
{
    public function __invoke(StoreQuickLendingLoanRequest $request, LendingLedger $ledger): JsonResponse
    {
        $data = $request->validated();
        $direction = $data['direction'];
        if (isset($data['contact_id'])) {
            $person = $ledger->contact($request->user(), $data['contact_id']);
            $contactIds = [$person->id];
            $direction = $ledger->direction($person, $request->user(), $direction);
        } else {
            $email = empty($data['person']['email']) ? null : mb_strtolower(trim($data['person']['email']));
            $contactIds = LendingContact::query()->where('owner_id', $request->user()->id)
                ->where(fn (Builder $query) => $query->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower(trim($data['person']['name']))])
                    ->when($email, fn (Builder $query) => $query->orWhere('email_hash', hash('sha256', $email))))->select('id');
        }
        $date = Carbon::parse($data['occurred_on']);
        $loans = $ledger->loans($request->user())->whereIn('lending_contact_id', $contactIds)->whereNull('voided_at')
            ->where('direction', $direction)->where('currency_code', $data['currency_code'])->where('amount_minor', $data['amount_minor'])
            ->where('occurred_on', '>=', $date->copy()->subDay()->toDateString())
            ->where('occurred_on', '<', $date->copy()->addDays(2)->toDateString());
        $count = (clone $loans)->count();

        return response()->json([
            'data' => LendingLoanResource::collection($loans->with('contact')->latest('occurred_on')->latest('id')->limit(5)->get())->resolve($request),
            'total' => $count,
        ]);
    }
}
