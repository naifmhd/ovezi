<?php

namespace App\Services;

use App\Exceptions\InvalidSplit;
use App\SplitType;
use Brick\Math\BigInteger;

class SplitCalculator
{
    /**
     * @param  array<string, int>  $values
     * @return array<string, int>
     */
    public function calculate(int $amountMinor, SplitType $splitType, array $values, string $payerKey): array
    {
        if ($amountMinor <= 0) {
            throw new InvalidSplit('The expense amount must be greater than zero.');
        }

        if ($values === []) {
            throw new InvalidSplit('At least one split participant is required.');
        }

        if (! array_key_exists($payerKey, $values)) {
            throw new InvalidSplit('The payer must be included in the split.');
        }

        return match ($splitType) {
            SplitType::Equal => $this->calculateEqual($amountMinor, $values, $payerKey),
            SplitType::Exact => $this->calculateExact($amountMinor, $values),
            SplitType::Percentage => $this->calculateWeighted($amountMinor, $values, $payerKey, 10_000),
            SplitType::Shares => $this->calculateWeighted($amountMinor, $values, $payerKey),
        };
    }

    /**
     * @param  array<string, int>  $values
     * @return array<string, int>
     */
    private function calculateEqual(int $amountMinor, array $values, string $payerKey): array
    {
        $participantCount = count($values);
        $share = intdiv($amountMinor, $participantCount);
        $allocations = array_fill_keys(array_keys($values), $share);
        $allocations[$payerKey] += $amountMinor % $participantCount;

        return $allocations;
    }

    /**
     * @param  array<string, int>  $values
     * @return array<string, int>
     */
    private function calculateExact(int $amountMinor, array $values): array
    {
        $total = BigInteger::zero();

        foreach ($values as $value) {
            if (! is_int($value) || $value < 0) {
                throw new InvalidSplit('Exact split amounts cannot be negative.');
            }

            $total = $total->plus($value);
        }

        if (! $total->isEqualTo($amountMinor)) {
            throw new InvalidSplit('Exact split amounts must equal the expense amount.');
        }

        return $values;
    }

    /**
     * @param  array<string, int>  $weights
     * @return array<string, int>
     */
    private function calculateWeighted(
        int $amountMinor,
        array $weights,
        string $payerKey,
        ?int $requiredWeightTotal = null,
    ): array {
        $weightTotal = BigInteger::zero();

        foreach ($weights as $weight) {
            if (! is_int($weight) || $weight <= 0) {
                throw new InvalidSplit('Split weights must be positive integers.');
            }

            $weightTotal = $weightTotal->plus($weight);
        }

        if ($requiredWeightTotal !== null && ! $weightTotal->isEqualTo($requiredWeightTotal)) {
            throw new InvalidSplit('Percentage splits must total 100.00%.');
        }

        $allocations = [];
        $allocatedMinor = 0;
        $amount = BigInteger::of($amountMinor);

        foreach ($weights as $participantKey => $weight) {
            $allocation = $amount
                ->multipliedBy($weight)
                ->quotient($weightTotal)
                ->toInt();

            $allocations[$participantKey] = $allocation;
            $allocatedMinor += $allocation;
        }

        $allocations[$payerKey] += $amountMinor - $allocatedMinor;

        return $allocations;
    }
}
