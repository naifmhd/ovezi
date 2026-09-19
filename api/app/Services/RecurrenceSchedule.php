<?php

namespace App\Services;

use App\RecurrenceFrequency;
use Carbon\CarbonImmutable;

class RecurrenceSchedule
{
    public function next(
        CarbonImmutable $startOn,
        CarbonImmutable $currentOccurrence,
        RecurrenceFrequency $frequency,
    ): CarbonImmutable {
        return match ($frequency) {
            RecurrenceFrequency::Weekly => $currentOccurrence->addWeek(),
            RecurrenceFrequency::Monthly => $this->nextMonthly($startOn, $currentOccurrence),
            RecurrenceFrequency::Yearly => $this->nextYearly($startOn, $currentOccurrence),
        };
    }

    public function firstAfter(
        CarbonImmutable $startOn,
        CarbonImmutable $after,
        RecurrenceFrequency $frequency,
    ): CarbonImmutable {
        $occurrence = $startOn;

        while ($occurrence->lessThanOrEqualTo($after)) {
            $occurrence = $this->next($startOn, $occurrence, $frequency);
        }

        return $occurrence;
    }

    private function nextMonthly(CarbonImmutable $startOn, CarbonImmutable $currentOccurrence): CarbonImmutable
    {
        $targetMonth = $currentOccurrence->startOfMonth()->addMonth();

        return $targetMonth->day(min($startOn->day, $targetMonth->daysInMonth));
    }

    private function nextYearly(CarbonImmutable $startOn, CarbonImmutable $currentOccurrence): CarbonImmutable
    {
        $targetMonth = CarbonImmutable::create(
            $currentOccurrence->year + 1,
            $startOn->month,
            1,
        );

        return $targetMonth->day(min($startOn->day, $targetMonth->daysInMonth));
    }
}
