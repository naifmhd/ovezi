<?php

namespace App\Services;

use App\SplitType;
use Brick\Math\BigInteger;
use Illuminate\Validation\ValidationException;

class ExpenseAllocation
{
    public function __construct(
        private readonly SplitCalculator $splitCalculator,
        private readonly ReportingSplitAllocator $reportingAllocator,
    ) {}

    /**
     * @param  list<array{user_id?: int|null, placeholder_id?: int|null, value?: int, amount_paid_minor?: int|null, included_in_split?: bool}>  $participants
     * @return list<array<string, mixed>>
     */
    public function calculate(int $amount, int $reportingAmount, SplitType $type, array $participants, ?int $payerUserId, ?int $payerPlaceholderId): array
    {
        $payerKey = $this->key(['user_id' => $payerUserId, 'placeholder_id' => $payerPlaceholderId]);
        $rows = [];
        $values = [];
        $paid = [];
        $hasContributions = false;
        foreach ($participants as $participant) {
            $key = $this->key($participant);
            if (isset($rows[$key])) {
                throw ValidationException::withMessages(['participants' => 'Each participant may only appear once.']);
            }
            $rows[$key] = $participant;
            if ($participant['included_in_split'] ?? true) {
                if ($type !== SplitType::Equal && ! isset($participant['value'])) {
                    throw ValidationException::withMessages(['participants' => 'Enter a split value for everyone included.']);
                }
                $values[$key] = $participant['value'] ?? 1;
            }
            if (isset($participant['amount_paid_minor'])) {
                $hasContributions = true;
                $payment = $participant['amount_paid_minor'];
                if (! is_int($payment) || $payment < 0 || $payment > $amount) {
                    throw ValidationException::withMessages(['participants' => 'Payment amounts must be between zero and the expense total.']);
                }
                $paid[$key] = $payment;
            }
        }
        if ($hasContributions) {
            if (count($paid) !== count($rows) || ! BigInteger::sum(...array_values($paid))->isEqualTo($amount) || ($paid[$payerKey] ?? 0) <= 0) {
                throw ValidationException::withMessages(['participants' => 'Enter what each person paid. Payments must equal the expense total and include the selected payer.']);
            }
        } else {
            $paid = [$payerKey => $amount];
            $rows[$payerKey] ??= ['user_id' => $payerUserId, 'placeholder_id' => $payerPlaceholderId, 'included_in_split' => false];
        }
        $owed = $this->splitCalculator->calculate($amount, $type, $values, $payerKey);
        $reportingOwed = $this->reportingAllocator->allocate($reportingAmount, $owed, $payerKey);
        $reportingPaid = $this->reportingAllocator->allocate($reportingAmount, $paid, $payerKey);

        $result = [];
        foreach ($rows as $key => $participant) {
            $included = $participant['included_in_split'] ?? true;
            $result[] = [
                'user_id' => $participant['user_id'] ?? null,
                'placeholder_id' => $participant['placeholder_id'] ?? null,
                'included_in_split' => $included,
                'amount_owed_minor' => $owed[$key] ?? 0,
                'reporting_amount_owed_minor' => $reportingOwed[$key] ?? 0,
                'amount_paid_minor' => $paid[$key] ?? 0,
                'reporting_amount_paid_minor' => $reportingPaid[$key] ?? 0,
                'split_type' => $type,
                'split_value' => ! $included || $type === SplitType::Equal ? null : $participant['value'],
            ];
        }

        return $result;
    }

    private function key(array $participant): string
    {
        $userId = $participant['user_id'] ?? null;
        $placeholderId = $participant['placeholder_id'] ?? null;
        if (($userId === null) === ($placeholderId === null)) {
            throw ValidationException::withMessages(['participants' => 'Select exactly one user or placeholder.']);
        }

        return $userId !== null ? "user:{$userId}" : "placeholder:{$placeholderId}";
    }
}
