<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\DomainChanged;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LendingContactResource;
use App\Models\LendingContact;
use App\Services\LendingLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LendingContactController extends Controller
{
    public function index(Request $request, LendingLedger $ledger): AnonymousResourceCollection
    {
        $data = $request->validate(['today' => ['sometimes', 'date_format:Y-m-d'], 'q' => ['nullable', 'string', 'max:120'], 'page' => ['sometimes', 'integer', 'min:1'], 'direction' => ['sometimes', Rule::in(['lent', 'borrowed'])], 'status' => ['sometimes', Rule::in(['outstanding', 'overdue', 'due_soon', 'settled', 'all'])]]);
        $visibleLoans = function ($query) use ($request, $data): void {
            $query->whereNull('voided_at')->where(fn ($query) => $query->whereHas('contact', fn ($query) => $query->where('owner_id', $request->user()->id))->orWhere('shared_with', $request->user()->id));
            if (isset($data['direction'])) {
                $opposite = $data['direction'] === 'lent' ? 'borrowed' : 'lent';
                $query->where(fn ($query) => $query->where(fn ($query) => $query->whereHas('contact', fn ($query) => $query->where('owner_id', $request->user()->id))->where('direction', $data['direction']))
                    ->orWhere(fn ($query) => $query->where('shared_with', $request->user()->id)->where('direction', $opposite)));
            }
        };
        $today = \Carbon\CarbonImmutable::parse($data['today'] ?? now()->toDateString())->startOfDay();
        $contacts = $ledger->contacts($request->user())->with('owner:id,name')
            ->addSelect(['lending_contacts.*', 'pin_id' => DB::table('user_pins')->select('id')->whereColumn('lending_contact_id', 'lending_contacts.id')->where('user_id', $request->user()->id)])
            ->orderByRaw('CASE WHEN pin_id IS NULL THEN 1 ELSE 0 END')
            ->when(($data['status'] ?? 'all') === 'outstanding', fn ($query) => $query->whereHas('loans', function ($query) use ($visibleLoans): void {
                $visibleLoans($query);
                $query->whereColumn('amount_minor', '>', 'repaid_minor');
            }))
            ->when(in_array($data['status'] ?? '', ['overdue', 'due_soon']), fn ($query) => $query->whereHas('loans', function ($query) use ($visibleLoans, $data, $today): void {
                $visibleLoans($query);
                $query->whereColumn('amount_minor', '>', 'repaid_minor');
                if ($data['status'] === 'overdue') {
                    $query->where('due_on', '<', $today);
                } else {
                    $query->where('due_on', '>=', $today)->where('due_on', '<', $today->addDays(8));
                }
            }))
            ->when(($data['status'] ?? 'all') === 'settled', fn ($query) => $query->whereHas('loans', $visibleLoans)->whereDoesntHave('loans', function ($query) use ($visibleLoans): void {
                $visibleLoans($query);
                $query->whereColumn('amount_minor', '>', 'repaid_minor');
            }))->when($data['q'] ?? null, fn ($query, $q) => $query->where(function ($query) use ($q, $request): void {
                $query->where(fn ($query) => $query->where('owner_id', $request->user()->id)->where('name', 'like', '%'.$q.'%'))
                    ->orWhere(fn ($query) => $query->where('owner_id', '!=', $request->user()->id)->whereHas('owner', fn ($query) => $query->where('name', 'like', '%'.$q.'%')));
            }))->latest('updated_at')->latest('id')->paginate(30);
        $due = $ledger->loans($request->user())->whereIn('lending_contact_id', $contacts->pluck('id'))->where($visibleLoans)
            ->whereColumn('amount_minor', '>', 'repaid_minor')->selectRaw('lending_contact_id, MIN(due_on) as next_due_on')->groupBy('lending_contact_id')->pluck('next_due_on', 'lending_contact_id');
        $contacts->each(fn ($contact) => $contact->setAttribute('next_due_on', $due->get($contact->id) ? substr($due->get($contact->id), 0, 10) : null));
        $balances = collect($ledger->balances($request->user(), $contacts->pluck('id')->all()))->groupBy('lending_contact_id');
        $contacts->each(fn ($contact) => $contact->setAttribute('balances', $balances->get($contact->id, collect())->all()));

        return LendingContactResource::collection($contacts);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['nullable', 'email:rfc', 'max:254']]);
        $email = empty($data['email']) ? null : mb_strtolower(trim($data['email']));
        $contact = LendingContact::create(['owner_id' => $request->user()->id, 'name' => trim($data['name']), 'email' => $email, 'email_hash' => $email ? hash('sha256', $email) : null]);

        return LendingContactResource::make($contact)->response()->setStatusCode(201);
    }

    public function show(Request $request, int $contact, LendingLedger $ledger): LendingContactResource
    {
        $person = $ledger->contact($request->user(), $contact)->load('owner:id,name');
        $person->setAttribute('balances', $ledger->balances($request->user(), [$contact]));
        $person->setAttribute('last_currency_code', $ledger->loans($request->user())->where('lending_contact_id', $contact)->latest('id')->value('currency_code'));

        return LendingContactResource::make($person);
    }

    public function update(Request $request, int $contact, LendingLedger $ledger): LendingContactResource
    {
        $data = $request->validate(['name' => ['sometimes', 'required', 'string', 'max:120'], 'email' => ['sometimes', 'nullable', 'email:rfc', 'max:254']]);

        return DB::transaction(function () use ($request, $contact, $ledger, $data): LendingContactResource {
            $person = $ledger->contact($request->user(), $contact, true);
            abort_unless($person->owner_id === $request->user()->id, 403);
            if (array_key_exists('email', $data)) {
                $data['email'] = $data['email'] ? mb_strtolower(trim($data['email'])) : null;
            }
            if (array_key_exists('email', $data) && $data['email'] !== $person->email) {
                $ledger->ensure($person->connected_user_id === null, 'email', 'This connection is bound to a verified account. Create a different person to connect to someone else.');
                $data['email'] = $data['email'] ? mb_strtolower(trim($data['email'])) : null;
                $data['email_hash'] = $data['email'] ? hash('sha256', $data['email']) : null;
                $data['invitation_status'] = null;
                $data['invitation_expires_at'] = null;
                $person->loans()->whereNull('shared_with')->update(['shared_at' => null, 'acknowledgement' => 'private']);
            }
            $person->update($data);

            return LendingContactResource::make($person);
        });
    }

    public function disconnect(Request $request, int $contact, LendingLedger $ledger): JsonResponse
    {
        DB::transaction(function () use ($request, $contact, $ledger): void {
            $person = $ledger->contact($request->user(), $contact, true);
            $person->update(['disconnected_at' => now(), 'invitation_status' => 'disconnected']);
            $person->loans()->whereNull('shared_with')->update(['shared_at' => null, 'acknowledgement' => 'private']);
            DomainChanged::dispatch('lending', 'disconnected', $person->id, null, array_values(array_filter([$person->owner_id, $person->connected_user_id])));
        });

        return response()->json(['message' => 'Future sharing is stopped. Existing shared history remains available.']);
    }
}
