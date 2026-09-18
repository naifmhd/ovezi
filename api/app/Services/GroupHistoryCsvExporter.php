<?php

namespace App\Services;

use App\Models\Currency;
use App\Models\Expense;
use App\Models\Group;
use App\Models\Settlement;
use Illuminate\Support\Collection;

class GroupHistoryCsvExporter
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
                'creator:id,name',
                'splits.user:id,name',
                'splits.placeholder:id,name',
            ])
            ->get()
            ->map(fn (Expense $expense): array => [
                'sort_at' => $expense->occurred_at,
                'row' => [
                    'expense',
                    $expense->trashed() ? 'deleted' : 'active',
                    $expense->occurred_at->toIso8601String(),
                    $expense->description,
                    $expense->payerUser?->name ?? $expense->payerPlaceholder?->name,
                    $expense->splits->map(function ($split) use ($expense): string {
                        $name = $split->user?->name ?? $split->placeholder?->name ?? 'Unknown';

                        return "{$name}: {$this->decimalAmount($split->amount_owed_minor, $expense->currency)} {$expense->currency_code}";
                    })->implode(' | '),
                    $this->decimalAmount($expense->amount_minor, $expense->currency),
                    $expense->amount_minor,
                    $expense->currency_code,
                    $this->decimalAmount($expense->reporting_amount_minor, $expense->reportingCurrency),
                    $expense->reporting_amount_minor,
                    $expense->reporting_currency_code,
                    $expense->category,
                    null,
                    $expense->creator?->name,
                ],
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
                'creator:id,name',
            ])
            ->get()
            ->map(fn (Settlement $settlement): array => [
                'sort_at' => $settlement->occurred_at,
                'row' => [
                    'settlement',
                    $settlement->trashed() ? 'deleted' : 'active',
                    $settlement->occurred_at->toIso8601String(),
                    $settlement->note,
                    $settlement->fromUser?->name ?? $settlement->fromPlaceholder?->name,
                    $settlement->toUser?->name ?? $settlement->toPlaceholder?->name,
                    $this->decimalAmount($settlement->amount_minor, $settlement->currency),
                    $settlement->amount_minor,
                    $settlement->currency_code,
                    $this->decimalAmount($settlement->reporting_amount_minor, $settlement->reportingCurrency),
                    $settlement->reporting_amount_minor,
                    $settlement->reporting_currency_code,
                    null,
                    $settlement->method,
                    $settlement->creator?->name,
                ],
            ]);

        return $this->csv($expenses->concat($settlements)->sortBy('sort_at')->pluck('row'));
    }

    /** @param Collection<int, array<int, int|string|null>> $rows */
    private function csv(Collection $rows): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new \RuntimeException('Unable to create the group export.');
        }

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'record_type', 'status', 'occurred_at', 'description_or_note', 'payer_or_from',
            'participants_or_to', 'amount', 'amount_minor', 'currency', 'reporting_amount',
            'reporting_amount_minor', 'reporting_currency', 'category', 'method', 'created_by',
        ]);

        foreach ($rows as $row) {
            fputcsv($stream, array_map($this->safeCell(...), $row));
        }

        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        if ($contents === false) {
            throw new \RuntimeException('Unable to read the group export.');
        }

        return $contents;
    }

    private function safeCell(int|string|null $value): int|string|null
    {
        if (is_string($value) && preg_match('/^[=+\-@\t\r]/u', $value) === 1) {
            return "'{$value}";
        }

        return $value;
    }

    private function decimalAmount(int $minorAmount, ?Currency $currency): string
    {
        $factor = $currency?->minor_unit_factor ?? 100;
        $digits = 0;

        for ($remaining = $factor; $remaining > 1 && $remaining % 10 === 0; $remaining = intdiv($remaining, 10)) {
            $digits++;
        }

        return number_format($minorAmount / $factor, $digits, '.', '');
    }
}
