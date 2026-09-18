<?php

namespace App\Services;

class DebtSimplifier
{
    /**
     * @param  array<string, int>  $balances
     * @return list<array{from: string, to: string, amount_minor: int}>
     */
    public function simplify(array $balances): array
    {
        $debtors = [];
        $creditors = [];

        foreach ($balances as $participant => $balance) {
            if ($balance < 0) {
                $debtors[] = ['key' => $participant, 'amount' => -$balance];
            } elseif ($balance > 0) {
                $creditors[] = ['key' => $participant, 'amount' => $balance];
            }
        }

        $sort = fn (array $left, array $right): int => $right['amount'] <=> $left['amount']
            ?: $left['key'] <=> $right['key'];
        usort($debtors, $sort);
        usort($creditors, $sort);

        $transactions = [];
        $debtorIndex = 0;
        $creditorIndex = 0;

        while (isset($debtors[$debtorIndex], $creditors[$creditorIndex])) {
            $amount = min(
                $debtors[$debtorIndex]['amount'],
                $creditors[$creditorIndex]['amount'],
            );
            $transactions[] = [
                'from' => $debtors[$debtorIndex]['key'],
                'to' => $creditors[$creditorIndex]['key'],
                'amount_minor' => $amount,
            ];

            $debtors[$debtorIndex]['amount'] -= $amount;
            $creditors[$creditorIndex]['amount'] -= $amount;

            if ($debtors[$debtorIndex]['amount'] === 0) {
                $debtorIndex++;
            }

            if ($creditors[$creditorIndex]['amount'] === 0) {
                $creditorIndex++;
            }
        }

        return $transactions;
    }
}
