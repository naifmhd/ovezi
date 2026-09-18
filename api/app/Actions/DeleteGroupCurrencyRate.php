<?php

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\Currency;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class DeleteGroupCurrencyRate
{
    public function execute(User $actor, Group $group, Currency $baseCurrency): void
    {
        if ($group->archived_at !== null) {
            throw ValidationException::withMessages([
                'group' => 'Reopen the group before changing currency rates.',
            ]);
        }

        DB::transaction(function () use ($actor, $group, $baseCurrency): void {
            $currencyRate = $group->currencyRates()
                ->where('base_currency_code', $baseCurrency->code)
                ->where('quote_currency_code', $group->reporting_currency_code)
                ->lockForUpdate()
                ->first();

            if ($currencyRate === null) {
                throw new NotFoundHttpException;
            }

            ActivityLog::query()->create([
                'group_id' => $group->id,
                'actor_id' => $actor->id,
                'subject_type' => $currencyRate->getMorphClass(),
                'subject_id' => $currencyRate->id,
                'event' => 'currency_rate.deleted',
                'metadata' => [
                    'base_currency_code' => $currencyRate->base_currency_code,
                    'quote_currency_code' => $currencyRate->quote_currency_code,
                    'rate' => $currencyRate->rate,
                ],
            ]);

            $currencyRate->delete();
        });
    }
}
