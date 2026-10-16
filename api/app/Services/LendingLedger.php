<?php

namespace App\Services;

use App\Events\DomainChanged;
use App\Jobs\SendLendingUpdatePushNotification;
use App\Models\LendingContact;
use App\Models\LendingLoan;
use App\Models\LendingRepayment;
use App\Models\User;
use App\Notifications\LendingUpdate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LendingLedger
{
    public function contacts(User $user): Builder
    {
        return LendingContact::query()->where(fn (Builder $query) => $query->where('owner_id', $user->id)
            ->orWhereHas('loans', fn (Builder $loans) => $loans->where('shared_with', $user->id)));
    }

    public function loans(User $user): Builder
    {
        return LendingLoan::query()->where(fn (Builder $query) => $query
            ->whereHas('contact', fn (Builder $contacts) => $contacts->where('owner_id', $user->id))
            ->orWhere('shared_with', $user->id));
    }

    public function contact(User $user, int $id, bool $lock = false): LendingContact
    {
        return $this->contacts($user)->when($lock, fn (Builder $query) => $query->lockForUpdate())->findOrFail($id);
    }

    public function direction(LendingContact $contact, User $user, string $direction): string
    {
        return $contact->owner_id === $user->id ? $direction : ($direction === 'lent' ? 'borrowed' : 'lent');
    }

    /** @return list<array<string, mixed>> */
    public function balances(User $user, array $contactIds): array
    {
        return $this->loans($user)->whereIn('lending_contact_id', $contactIds)->whereNull('voided_at')
            ->selectRaw('lending_contact_id, currency_code, direction, SUM(amount_minor) as lent_minor, SUM(repaid_minor) as repaid_minor, SUM(amount_minor - repaid_minor) as outstanding_minor')
            ->groupBy('lending_contact_id', 'currency_code', 'direction')->get()->toArray();
    }

    public function createLoan(User $user, int $contactId, array $data): LendingLoan
    {
        return DB::transaction(function () use ($user, $contactId, $data): LendingLoan {
            $contact = $this->contact($user, $contactId, true);
            $shared = (bool) ($data['shared'] ?? false);
            if ($contact->owner_id !== $user->id) {
                abort_unless($shared && $contact->connected_user_id === $user->id && ! $contact->disconnected_at, 403);
            }
            if ($shared) {
                $this->ensure((bool) config('ovezi.lending_sharing_enabled'), 'shared', 'New lending connections and sharing are not enabled yet. Private tracking remains available.');
                $this->ensure($contact->connected_user_id !== null && $contact->disconnected_at === null, 'shared', 'Connect with this person before sharing new loans.');
            }
            unset($data['shared']);
            $loan = $contact->loans()->create([
                ...$data, 'direction' => $this->direction($contact, $user, $data['direction']), 'created_by' => $user->id,
                'shared_at' => $shared ? now() : null, 'shared_with' => $shared ? $contact->connected_user_id : null,
                'acknowledgement' => $shared ? 'pending' : 'private',
            ]);
            $this->event($loan, $user, 'loan.created');
            $contact->touch();

            return $loan->load('contact');
        }, 3);
    }

    /** A single currency payment may allocate across loans with different confirmation requirements. */
    public function repay(User $user, int $contactId, array $data): array
    {
        return DB::transaction(function () use ($user, $contactId, $data): array {
            $contact = $this->contact($user, $contactId, true);
            $direction = $this->direction($contact, $user, $data['direction']);
            $loans = $this->loans($user)->where('lending_contact_id', $contact->id)
                ->where('currency_code', $data['currency_code'])->where('direction', $direction)
                ->whereNull('voided_at')->whereNull('void_requested_by')
                ->whereColumn('amount_minor', '>', 'repaid_minor')
                ->when($data['loan_id'] ?? null, fn (Builder $query, $id) => $query->whereKey($id))
                ->orderBy('occurred_on')->orderBy('id')->lockForUpdate()->get();
            $remaining = (int) $data['amount_minor'];
            $allocations = [];
            foreach ($loans as $loan) {
                $pending = (int) DB::table('lending_repayment_allocations')->join('lending_repayments', 'lending_repayments.id', '=', 'lending_repayment_allocations.lending_repayment_id')
                    ->where('lending_loan_id', $loan->id)->where('status', 'pending')->sum('lending_repayment_allocations.amount_minor');
                $amount = min($remaining, $loan->amount_minor - $loan->repaid_minor - $pending);
                if ($amount <= 0) {
                    continue;
                }
                $this->ensure($data['occurred_on'] >= $loan->occurred_on->toDateString(), 'occurred_on', 'A repayment cannot be dated before its loan.');
                $allocations[] = [$loan, $amount];
                $remaining -= $amount;
                if ($remaining === 0) {
                    break;
                }
            }
            $this->ensure($remaining === 0, 'amount_minor', 'This amount exceeds the available balance in this currency. Pending repayments are already reserved.');
            $repayments = [];
            foreach ($allocations as [$loan, $amount]) {
                $confirmed = $loan->shared_with === null || $this->isLender($loan, $contact, $user);
                $repayment = $contact->repayments()->create([
                    'recorded_by' => $user->id, 'confirmed_by' => $confirmed ? $user->id : null,
                    'amount_minor' => $amount, 'currency_code' => $data['currency_code'], 'direction' => $direction,
                    'occurred_on' => $data['occurred_on'], 'note' => $data['note'] ?? null, 'status' => $confirmed ? 'confirmed' : 'pending',
                ]);
                $repayment->loans()->attach($loan->id, ['amount_minor' => $amount]);
                if ($confirmed) {
                    $loan->increment('repaid_minor', $amount);
                }
                $this->event($loan, $user, $confirmed ? 'repayment.confirmed' : 'repayment.reported', ['repayment_id' => $repayment->id, 'amount_minor' => $amount]);
                $repayments[] = ['id' => $repayment->id, 'status' => $repayment->status, 'amount_minor' => $amount, 'loan_id' => $loan->id];
            }
            $contact->touch();

            return $repayments;
        }, 3);
    }

    public function changeRepayment(User $user, int $id, string $action): void
    {
        DB::transaction(function () use ($user, $id, $action): void {
            $repayment = LendingRepayment::query()->findOrFail($id);
            $contact = $this->contact($user, $repayment->lending_contact_id, true);
            $repayment = $repayment->fresh();
            $loan = $this->loans($user)->whereHas('repayments', fn (Builder $query) => $query->whereKey($id))->firstOrFail();
            $lender = $loan->shared_with === null ? $contact->owner_id === $user->id : $this->isLender($loan, $contact, $user);
            if ($action === 'confirm') {
                abort_unless($lender, 403);
                $this->ensure($repayment->status === 'pending', 'action', 'Only pending repayments can be confirmed.');
                $this->ensure(! $loan->voided_at && $loan->repaid_minor + $repayment->amount_minor <= $loan->amount_minor, 'amount_minor', 'This repayment exceeds the outstanding balance.');
                $loan->increment('repaid_minor', $repayment->amount_minor);
                $repayment->update(['status' => 'confirmed', 'confirmed_by' => $user->id]);
            } elseif ($action === 'reject') {
                abort_unless($lender || $repayment->recorded_by === $user->id, 403);
                $this->ensure($repayment->status === 'pending', 'action', 'Only pending repayments can be withdrawn or rejected.');
                $repayment->update(['status' => 'rejected']);
            } else {
                abort_unless($lender, 403);
                $this->ensure($repayment->status === 'confirmed', 'action', 'Only confirmed repayments can be reversed.');
                $loan->decrement('repaid_minor', $repayment->amount_minor);
                $repayment->update(['status' => 'reversed']);
            }
            $this->event($loan, $user, 'repayment.'.$action, ['repayment_id' => $id, 'amount_minor' => $repayment->amount_minor]);
            $contact->touch();
        }, 3);
    }

    public function changeLoan(User $user, int $id, string $action): LendingLoan
    {
        return DB::transaction(function () use ($user, $id, $action): LendingLoan {
            $visible = $this->loans($user)->findOrFail($id);
            $contact = $this->contact($user, $visible->lending_contact_id, true);
            $loan = $this->loans($user)->findOrFail($id);
            $this->ensure(! $loan->voided_at, 'action', 'This loan has already been voided.');
            if (in_array($action, ['acknowledge', 'dispute'])) {
                abort_unless($loan->shared_with !== null && $loan->created_by !== $user->id, 403);
                $loan->update(['acknowledgement' => $action === 'acknowledge' ? 'acknowledged' : 'disputed']);
            } else {
                $this->ensure($loan->repaid_minor === 0 && ! $loan->repayments()->where('status', 'pending')->exists(), 'action', 'Reverse confirmed repayments and resolve pending repayments before correcting this loan.');
                if ($action === 'void') {
                    abort_unless($loan->created_by === $user->id, 403);
                    $this->ensure($loan->void_requested_by === null, 'action', 'A correction is already awaiting confirmation.');
                    $loan->update($loan->shared_with === null ? ['voided_at' => now(), 'shared_at' => null, 'acknowledgement' => 'private'] : ['void_requested_by' => $user->id]);
                } else {
                    abort_unless($loan->void_requested_by !== null && $loan->void_requested_by !== $user->id, 403);
                    $loan->update(['voided_at' => $action === 'confirm_void' ? now() : null, 'void_requested_by' => null]);
                }
            }
            $this->event($loan, $user, 'loan.'.$action);

            return $loan->load('contact');
        }, 3);
    }

    public function isLender(LendingLoan $loan, LendingContact $contact, User $user): bool
    {
        return ($loan->direction === 'lent' ? $contact->owner_id : $loan->shared_with) === $user->id;
    }

    public function event(LendingLoan $loan, User $user, string $event, array $details = []): void
    {
        $loan->events()->create(['actor_id' => $user->id, 'event' => $event, 'details' => $details]);
        DomainChanged::dispatch('lending', $event, $loan->id, null, array_values(array_unique(array_filter([$loan->contact->owner_id, $loan->shared_with]))));
        if ($loan->shared_with !== null) {
            $recipient = $loan->contact->owner_id === $user->id ? $loan->shared_with : $loan->contact->owner_id;
            SendLendingUpdatePushNotification::dispatch($loan->id, $recipient)->afterCommit();
            User::query()->find($recipient)?->notify((new LendingUpdate($loan->lending_contact_id))->afterCommit());
        }
    }

    public function ensure(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
