<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\DomainChanged;
use App\Http\Controllers\Controller;
use App\Models\LendingContact;
use App\Models\User;
use App\Notifications\LendingInvitation;
use App\Services\LendingLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;

class LendingInvitationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        if (! $request->user()->hasVerifiedEmail()) {
            return response()->json(['data' => [], 'next_page_url' => null]);
        }
        $contacts = LendingContact::query()->where('email_hash', hash('sha256', mb_strtolower(trim($request->user()->email))))
            ->where('owner_id', '!=', $request->user()->id)->whereNull('connected_user_id')
            ->where('invitation_status', 'pending')->where('invitation_expires_at', '>', now())
            ->with('owner:id,name')->withCount(['loans' => fn ($query) => $query->whereNotNull('shared_at')])->latest('id')->paginate(30);
        $contacts->through(fn ($contact) => ['id' => $contact->id, 'sender_name' => $contact->owner->name, 'loan_count' => $contact->loans_count, 'expires_at' => $contact->invitation_expires_at]);

        return response()->json($contacts);
    }

    public function store(Request $request, int $contact, LendingLedger $ledger): JsonResponse
    {
        $ledger->ensure((bool) config('ovezi.lending_sharing_enabled'), 'shared', 'New lending connections and sharing are not enabled yet.');
        $data = $request->validate(['loan_ids' => ['required', 'array', 'min:1', 'max:100'], 'loan_ids.*' => ['required', 'integer', 'distinct']]);
        DB::transaction(function () use ($request, $contact, $ledger, $data): void {
            $person = $ledger->contact($request->user(), $contact, true);
            abort_unless($person->owner_id === $request->user()->id, 403);
            $ledger->ensure($request->user()->hasVerifiedEmail(), 'email', 'Verify your email before sharing lending records.');
            $ledger->ensure($person->email !== null && $person->email_hash !== hash('sha256', mb_strtolower(trim($request->user()->email))), 'email', 'Add the other person’s email before sharing.');
            $ledger->ensure($person->disconnected_at === null, 'email', 'This connection has been disconnected. Existing shared history remains available.');
            $loans = $person->loans()->whereKey($data['loan_ids'])->whereNull('voided_at')->whereNull('shared_at')->lockForUpdate()->get();
            $ledger->ensure($loans->count() === count($data['loan_ids']), 'loan_ids', 'Select only private, active loans belonging to this person.');
            foreach ($loans as $loan) {
                $loan->update(['shared_at' => now(), 'shared_with' => $person->connected_user_id, 'acknowledgement' => 'pending']);
                $ledger->event($loan, $request->user(), 'loan.shared');
            }
            if ($person->connected_user_id === null) {
                $person->update(['invitation_status' => 'pending', 'invitation_expires_at' => now()->addDays(14)]);
                $recipient = User::query()->whereRaw('LOWER(email) = ?', [$person->email])->whereNotNull('email_verified_at')->first();
                if ($recipient) {
                    DomainChanged::dispatch('lending', 'invited', $person->id, null, [$recipient->id]);
                }

                Notification::route('mail', $person->email)->notify((new LendingInvitation($request->user()->name))->afterCommit());
            }
        }, 3);

        return response()->json(['message' => 'Selected loans shared. A person who is not connected must accept the invitation first.']);
    }

    public function update(Request $request, int $contact, LendingLedger $ledger): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['accept', 'decline', 'revoke', 'resend'])]]);
        DB::transaction(function () use ($request, $contact, $ledger, $data): void {
            $person = LendingContact::query()->lockForUpdate()->findOrFail($contact);
            if (in_array($data['action'], ['revoke', 'resend'])) {
                abort_unless($person->owner_id === $request->user()->id, 404);
                $ledger->ensure($person->connected_user_id === null && $person->invitation_status === 'pending', 'action', 'There is no pending invitation.');
                if ($data['action'] === 'resend') {
                    $ledger->ensure($request->user()->hasVerifiedEmail(), 'email', 'Verify your email before sharing lending records.');
                    $person->update(['invitation_expires_at' => now()->addDays(14)]);
                    Notification::route('mail', $person->email)->notify((new LendingInvitation($request->user()->name))->afterCommit());

                    return;
                }
            } else {
                abort_unless($request->user()->hasVerifiedEmail() && $person->owner_id !== $request->user()->id
                    && $person->email_hash === hash('sha256', mb_strtolower(trim($request->user()->email))), 404);
                $ledger->ensure($person->connected_user_id === null && $person->invitation_status === 'pending' && $person->invitation_expires_at?->isFuture(), 'action', 'This invitation has expired or is no longer available.');
            }
            if ($data['action'] === 'accept') {
                $person->update(['connected_user_id' => $request->user()->id, 'invitation_status' => 'accepted']);
                foreach ($person->loans()->whereNotNull('shared_at')->whereNull('shared_with')->get() as $loan) {
                    $loan->update(['shared_with' => $request->user()->id]);
                    $ledger->event($loan, $request->user(), 'connection.accepted');
                }
            } else {
                $person->update(['invitation_status' => $data['action'] === 'decline' ? 'declined' : 'revoked']);
                $person->loans()->whereNull('shared_with')->update(['shared_at' => null, 'acknowledgement' => 'private']);
            }
        }, 3);

        return response()->json(['message' => 'Invitation updated.']);
    }
}
