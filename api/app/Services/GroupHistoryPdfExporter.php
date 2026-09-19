<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Expense;
use App\Models\Group;
use App\Models\Settlement;
use Dompdf\Dompdf;
use Dompdf\Options;

class GroupHistoryPdfExporter
{
    public function export(Group $group): string
    {
        $expenses = $group->expenses()
            ->withTrashed()
            ->with([
                'currency:code,minor_unit_factor',
                'reportingCurrency:code,minor_unit_factor',
                'payerUser:id,name',
                'payerPlaceholder:id,name',
                'splits.user:id,name',
                'splits.placeholder:id,name',
            ])
            ->get()
            ->map(fn (Expense $expense): array => [
                'sort_at' => $expense->occurred_at,
                'date' => $expense->occurred_at->format('d M Y'),
                'type' => 'Expense',
                'status' => $expense->trashed() ? 'Deleted' : 'Active',
                'description' => $expense->description,
                'from' => $expense->payerUser?->name ?? $expense->payerPlaceholder?->name ?? 'Unknown',
                'to' => $expense->splits->map(function ($split): string {
                    return $split->user?->name ?? $split->placeholder?->name ?? 'Unknown';
                })->implode(', '),
                'amount' => $this->money($expense->amount_minor, $expense->currency_code, $expense->currency),
                'reporting_amount' => $this->money(
                    $expense->reporting_amount_minor,
                    $expense->reporting_currency_code,
                    $expense->reportingCurrency,
                ),
                'detail' => $expense->category,
            ]);

        $settlements = $group->settlements()
            ->withTrashed()
            ->with([
                'currency:code,minor_unit_factor',
                'reportingCurrency:code,minor_unit_factor',
                'fromUser:id,name',
                'fromPlaceholder:id,name',
                'toUser:id,name',
                'toPlaceholder:id,name',
            ])
            ->get()
            ->map(fn (Settlement $settlement): array => [
                'sort_at' => $settlement->occurred_at,
                'date' => $settlement->occurred_at->format('d M Y'),
                'type' => 'Settlement',
                'status' => $settlement->trashed() ? 'Deleted' : 'Active',
                'description' => $settlement->note ?: 'Settlement',
                'from' => $settlement->fromUser?->name ?? $settlement->fromPlaceholder?->name ?? 'Unknown',
                'to' => $settlement->toUser?->name ?? $settlement->toPlaceholder?->name ?? 'Unknown',
                'amount' => $this->money($settlement->amount_minor, $settlement->currency_code, $settlement->currency),
                'reporting_amount' => $this->money(
                    $settlement->reporting_amount_minor,
                    $settlement->reporting_currency_code,
                    $settlement->reportingCurrency,
                ),
                'detail' => str($settlement->method)->replace('_', ' ')->title()->toString(),
            ]);

        $html = view('exports.group-history', [
            'group' => $group,
            'records' => $expenses->concat($settlements)->sortBy('sort_at')->values(),
            'generatedAt' => now(),
        ])->render();

        $options = new Options;
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('a4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    private function money(int $minorAmount, string $currencyCode, ?Currency $currency): string
    {
        $factor = $currency?->minor_unit_factor ?? 100;
        $digits = 0;

        for ($remaining = $factor; $remaining > 1 && $remaining % 10 === 0; $remaining = intdiv($remaining, 10)) {
            $digits++;
        }

        return number_format($minorAmount / $factor, $digits, '.', ',').' '.$currencyCode;
    }
}
