<?php

namespace App\Services;

use Brick\Math\BigInteger;
use InvalidArgumentException;

class ReportingSplitAllocator
{
    /**
     * @param  array<string, int>  $baseAllocations
     * @return array<string, int>
     */
    public function allocate(int $reportingAmountMinor, array $baseAllocations, string $payerKey): array
    {
        if ($reportingAmountMinor < 0 || $baseAllocations === []) {
            throw new InvalidArgumentException('Reporting allocations require a non-negative amount and participants.');
        }

        if (! array_key_exists($payerKey, $baseAllocations)) {
            throw new InvalidArgumentException('The payer must be included in reporting allocations.');
        }

        $baseTotal = BigInteger::zero();

        foreach ($baseAllocations as $allocation) {
            if (! is_int($allocation) || $allocation < 0) {
                throw new InvalidArgumentException('Base allocations must be non-negative integers.');
            }

            $baseTotal = $baseTotal->plus($allocation);
        }

        if ($baseTotal->isZero()) {
            throw new InvalidArgumentException('Base allocations must total more than zero.');
        }

        $reportingTotal = BigInteger::of($reportingAmountMinor);
        $allocations = [];
        $allocatedToOthers = 0;

        foreach ($baseAllocations as $participantKey => $baseAllocation) {
            if ($participantKey === $payerKey) {
                continue;
            }

            $allocation = $reportingTotal
                ->multipliedBy($baseAllocation)
                ->quotient($baseTotal)
                ->toInt();
            $allocations[$participantKey] = $allocation;
            $allocatedToOthers += $allocation;
        }

        $allocations[$payerKey] = $reportingAmountMinor - $allocatedToOthers;

        return array_replace(array_fill_keys(array_keys($baseAllocations), 0), $allocations);
    }
}
