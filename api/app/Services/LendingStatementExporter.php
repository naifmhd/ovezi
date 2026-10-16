<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\LendingContact;
use App\Models\User;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LendingStatementExporter
{
    public function __construct(private LendingLedger $ledger) {}

    public function statement(User $user, LendingContact $person, ?string $from, ?string $to): array
    {
        $visible = $this->ledger->loans($user)->where('lending_contact_id', $person->id);
        $dates = function ($query, string $column) use ($from, $to): void {
            $query->when($from, fn ($query) => $query->whereDate($column, '>=', $from))
                ->when($to, fn ($query) => $query->whereDate($column, '<=', $to));
        };
        $loans = (clone $visible)->where(fn ($query) => $dates($query, 'occurred_on'))->orderBy('occurred_on')->orderBy('id')->limit(5001)->get();
        $payments = DB::table('lending_repayment_allocations as a')->join('lending_repayments as r', 'r.id', '=', 'a.lending_repayment_id')
            ->join('lending_loans as l', 'l.id', '=', 'a.lending_loan_id')->whereIn('l.id', (clone $visible)->select('id'))
            ->where(fn ($query) => $dates($query, 'r.occurred_on'))->orderBy('r.occurred_on')->orderBy('a.id')->limit(5001)
            ->get(['a.id', 'l.id as loan_id', 'l.direction', 'l.currency_code', 'l.description', 'a.amount_minor', 'r.occurred_on', 'r.status']);
        if ($loans->count() + $payments->count() > 5000) {
            throw ValidationException::withMessages(['from' => 'Choose a shorter date range to export up to 5,000 entries.']);
        }
        $direction = fn (string $value): string => $this->ledger->direction($person, $user, $value);
        $rows = $loans->map(fn ($loan) => [
            'type' => 'Loan', 'loan_id' => $loan->id, 'date' => $loan->occurred_on->toDateString(),
            'currency' => $loan->currency_code, 'direction' => $direction($loan->direction), 'amount_minor' => $loan->amount_minor,
            'description' => $loan->description, 'status' => $loan->voided_at ? 'voided' : $loan->acknowledgement,
        ])->concat($payments->map(fn ($payment) => [
            'type' => 'Repayment allocation', 'loan_id' => $payment->loan_id, 'date' => substr($payment->occurred_on, 0, 10),
            'currency' => $payment->currency_code, 'direction' => $direction($payment->direction), 'amount_minor' => (int) $payment->amount_minor,
            'description' => $payment->description, 'status' => $payment->status,
        ]))->sortBy('date')->values();
        $balances = collect($this->ledger->balances($user, [$person->id]))->map(fn ($balance) => [
            'currency' => $balance['currency_code'], 'direction' => $direction($balance['direction']),
            'amount_minor' => (int) $balance['lent_minor'], 'repaid_minor' => (int) $balance['repaid_minor'], 'outstanding_minor' => (int) $balance['outstanding_minor'],
        ]);
        $factors = Currency::query()->pluck('minor_unit_factor', 'code');
        $money = function (int $amount, string $code) use ($factors): string {
            $factor = (int) $factors->get($code, 100);
            return number_format($amount / $factor, (int) round(log10($factor)), '.', '');
        };
        return ['name' => $person->owner_id === $user->id ? $person->name : ($person->owner?->name ?? 'Deleted member'),
            'generated_at' => now()->utc()->format('Y-m-d H:i').' UTC', 'from' => $from, 'to' => $to,
            'rows' => $rows, 'balances' => $balances, 'money' => $money];
    }

    public function csv(array $statement): string
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\xEF\xBB\xBF");
        $write = function (array $row) use ($stream): void {
            fputcsv($stream, array_map(fn ($cell) => is_string($cell) && preg_match('/^[\s]*[=+@\-\t\r]/u', $cell) ? "'".$cell : $cell, $row), ',', '"', '');
        };
        $write(['Ovezi person statement', $statement['name'], 'Generated', $statement['generated_at']]);
        $write(['Activity dates', $statement['from'] ?? 'All dates', $statement['to'] ?? 'All dates']);
        $write(['Current balances across all dates; only confirmed repayments reduce balances. Each currency is separate.']);
        $write(['currency', 'direction', 'principal', 'confirmed_repaid', 'outstanding', 'principal_minor', 'confirmed_repaid_minor', 'outstanding_minor']);
        foreach ($statement['balances'] as $balance) {
            $write([$balance['currency'], $balance['direction'], $statement['money']($balance['amount_minor'], $balance['currency']), $statement['money']($balance['repaid_minor'], $balance['currency']), $statement['money']($balance['outstanding_minor'], $balance['currency']), $balance['amount_minor'], $balance['repaid_minor'], $balance['outstanding_minor']]);
        }
        $write([]);
        $write(['record_type', 'loan_id', 'date', 'currency', 'direction', 'amount', 'amount_minor', 'description', 'status']);
        foreach ($statement['rows'] as $row) {
            $write([$row['type'], $row['loan_id'], $row['date'], $row['currency'], $row['direction'], $statement['money']($row['amount_minor'], $row['currency']), $row['amount_minor'], $row['description'], $row['status']]);
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);
        return $contents;
    }

    public function pdf(array $statement): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('exports.lending-statement', $statement)->render());
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        return $pdf->output();
    }
}
