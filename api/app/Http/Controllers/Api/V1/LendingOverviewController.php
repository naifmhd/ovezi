<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LendingLoanResource;
use App\Models\LendingContact;
use App\Models\User;
use App\Services\LendingLedger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LendingOverviewController extends Controller
{
    private const KINDS = ['repayments', 'acknowledgements', 'corrections', 'disputed', 'overdue', 'due_soon'];

    public function __invoke(Request $request, LendingLedger $ledger): JsonResponse
    {
        $data = $request->validate(['today' => ['sometimes', 'date_format:Y-m-d']]);
        $user = $request->user();
        $today = $data['today'] ?? now()->toDateString();
        $visible = $ledger->loans($user)->whereNull('voided_at')
            ->join('lending_contacts', 'lending_contacts.id', '=', 'lending_loans.lending_contact_id')
            ->selectRaw("currency_code, amount_minor, repaid_minor, CASE WHEN lending_contacts.owner_id = ? THEN direction WHEN direction = 'lent' THEN 'borrowed' ELSE 'lent' END as viewer_direction", [$user->id]);
        $balances = DB::query()->fromSub($visible, 'visible_loans')
            ->selectRaw('currency_code, viewer_direction as direction, SUM(amount_minor) as amount_minor, SUM(repaid_minor) as repaid_minor, SUM(amount_minor - repaid_minor) as outstanding_minor')
            ->groupBy('currency_code', 'viewer_direction')->orderBy('currency_code')->orderBy('viewer_direction')->get()
            ->map(fn ($balance): array => [
                'currency_code' => $balance->currency_code, 'direction' => $balance->direction,
                'amount_minor' => (int) $balance->amount_minor, 'repaid_minor' => (int) $balance->repaid_minor,
                'outstanding_minor' => (int) $balance->outstanding_minor,
            ]);
        $counts = [];
        foreach (self::KINDS as $kind) {
            $counts[$kind] = $this->attentionQuery($ledger, $user, $kind, $today)->count();
        }
        $counts['invitations'] = $user->hasVerifiedEmail() ? LendingContact::query()
            ->where('email_hash', hash('sha256', mb_strtolower(trim($user->email))))
            ->where('owner_id', '!=', $user->id)->whereNull('connected_user_id')
            ->where('invitation_status', 'pending')->where('invitation_expires_at', '>', now())->count() : 0;

        return response()->json(['data' => ['balances' => $balances, 'attention_counts' => $counts]]);
    }

    public function attention(Request $request, LendingLedger $ledger): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(self::KINDS)], 'today' => ['sometimes', 'date_format:Y-m-d'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $loans = $this->attentionQuery($ledger, $request->user(), $data['kind'], $data['today'] ?? now()->toDateString())
            ->with('contact.owner:id,name')->withSum(['repayments as pending_minor' => fn (Builder $payments) => $payments->where('status', 'pending')], 'lending_repayment_allocations.amount_minor')->orderBy('due_on')->orderBy('id')->paginate(30);
        $loans->through(fn ($loan): array => [
            ...(new LendingLoanResource($loan))->resolve($request),
            'person_name' => $loan->contact->owner_id === $request->user()->id ? $loan->contact->name : ($loan->contact->owner?->name ?? 'Deleted member'),
        ]);

        return response()->json($loans);
    }

    private function attentionQuery(LendingLedger $ledger, User $user, string $kind, string $today): Builder
    {
        $query = $ledger->loans($user)->whereNull('voided_at');

        return match ($kind) {
            'repayments' => $query->whereHas('repayments', fn (Builder $payments) => $payments->where('status', 'pending'))
                ->where(fn (Builder $loans) => $loans
                    ->where(fn (Builder $owned) => $owned->whereHas('contact', fn (Builder $contacts) => $contacts->where('owner_id', $user->id))->where('direction', 'lent'))
                    ->orWhere(fn (Builder $shared) => $shared->where('shared_with', $user->id)->where('direction', 'borrowed'))),
            'acknowledgements' => $query->whereNotNull('shared_with')->where('created_by', '!=', $user->id)->where('acknowledgement', 'pending'),
            'corrections' => $query->whereNotNull('void_requested_by')->where('void_requested_by', '!=', $user->id),
            'disputed' => $query->where('acknowledgement', 'disputed'),
            'due_soon' => $query->where('due_on', '>=', $today)->where('due_on', '<', Carbon::parse($today)->addDays(8)->toDateString())->whereColumn('amount_minor', '>', 'repaid_minor'),
            'overdue' => $query->where('due_on', '<', $today)->whereColumn('amount_minor', '>', 'repaid_minor'),
        };
    }
}
