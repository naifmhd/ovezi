<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotificationPreference;
use App\Models\User;
use App\NotificationType;
use App\Services\LendingLedger;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LendingReminderController extends Controller
{
    public function show(Request $request, int $loan, LendingLedger $ledger): JsonResponse
    {
        $record = $ledger->loans($request->user())->findOrFail($loan);
        $reminder = DB::table('lending_reminders')->where('user_id', $request->user()->id)->where('lending_loan_id', $loan)->first();
        return response()->json(['data' => $reminder && ! $record->voided_at && $record->amount_minor > $record->repaid_minor ? [
            'remind_at' => CarbonImmutable::parse($reminder->remind_at)->utc()->toIso8601String(),
            'sent_at' => $reminder->sent_at ? CarbonImmutable::parse($reminder->sent_at)->utc()->toIso8601String() : null,
        ] : null]);
    }

    public function update(Request $request, int $loan, LendingLedger $ledger): JsonResponse
    {
        $data = $request->validate(['remind_at' => ['required', 'date', 'after:now', 'before:'.now()->addYear()->toIso8601String()]]);
        DB::transaction(function () use ($request, $loan, $ledger, $data): void {
            User::query()->lockForUpdate()->findOrFail($request->user()->id);
            $record = $ledger->loans($request->user())->findOrFail($loan);
            $ledger->ensure(! $record->voided_at && $record->amount_minor > $record->repaid_minor, 'remind_at', 'This loan is already settled or voided.');
            $preference = $request->user()->notificationPreference ?? new NotificationPreference;
            if (! $preference->allows(NotificationType::SettleUpReminders)) {
                throw ValidationException::withMessages(['remind_at' => 'Enable settlement reminders in Profile → Notifications first.']);
            }
            $ledger->ensure($request->user()->pushTokens()->whereNull('revoked_at')->exists(), 'remind_at', 'Enable notifications on your phone before setting a reminder.');
            DB::table('lending_reminders')->updateOrInsert(['user_id' => $request->user()->id, 'lending_loan_id' => $loan], [
                'remind_at' => CarbonImmutable::parse($data['remind_at'])->utc(), 'sent_at' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
        return $this->show($request, $loan, $ledger);
    }

    public function destroy(Request $request, int $loan, LendingLedger $ledger): JsonResponse
    {
        $ledger->loans($request->user())->findOrFail($loan);
        DB::transaction(function () use ($request, $loan): void {
            User::query()->lockForUpdate()->findOrFail($request->user()->id);
            DB::table('lending_reminders')->where('user_id', $request->user()->id)->where('lending_loan_id', $loan)->delete();
        });
        return response()->json(['data' => null]);
    }
}
