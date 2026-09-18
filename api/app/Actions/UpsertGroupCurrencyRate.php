<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Group;
use App\Models\GroupCurrencyRate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpsertGroupCurrencyRate
{
    public function execute(User $actor, Group $group, Currency $baseCurrency, string $rate): GroupCurrencyRate
    {
        if ($group->archived_at !== null) {
            throw ValidationException::withMessages([
                'group' => 'Reopen the group before changing currency rates.',
            ]);
        }

        if (! $baseCurrency->is_active) {
            throw ValidationException::withMessages(['currency' => 'The base currency is not active.']);
        }

        if ($baseCurrency->code === $group->reporting_currency_code) {
            throw ValidationException::withMessages([
                'currency' => 'A same-currency conversion rate is not needed.',
            ]);
        }

        return DB::transaction(function () use ($actor, $group, $baseCurrency, $rate): GroupCurrencyRate {
            $currencyRate = $group->currencyRates()
                ->where('base_currency_code', $baseCurrency->code)
                ->where('quote_currency_code', $group->reporting_currency_code)
                ->lockForUpdate()
                ->first();
            $event = 'currency_rate.updated';
            $before = $currencyRate?->rate;

            if ($currencyRate === null) {
                $currencyRate = $group->currencyRates()->create([
                    'base_currency_code' => $baseCurrency->code,
                    'quote_currency_code' => $group->reporting_currency_code,
                    'rate' => $rate,
                    'created_by' => $actor->id,
                ]);
                $event = 'currency_rate.created';
            } else {
                $currencyRate->update(['rate' => $rate]);
            }

            ActivityLog::query()->create([
                'group_id' => $group->id,
                'actor_id' => $actor->id,
                'subject_type' => $currencyRate->getMorphClass(),
                'subject_id' => $currencyRate->id,
                'event' => $event,
                'metadata' => [
                    'base_currency_code' => $baseCurrency->code,
                    'quote_currency_code' => $group->reporting_currency_code,
                    'before' => $before,
                    'after' => $currencyRate->rate,
                ],
            ]);

            return $currencyRate->refresh();
        });
    }
}
