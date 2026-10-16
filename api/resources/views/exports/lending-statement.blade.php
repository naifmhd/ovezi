<!doctype html>
<html><head><meta charset="utf-8"><style>
@page { margin: 30px; } body { font-family: "DejaVu Sans", sans-serif; font-size: 10px; color: #0d1830; }
h1 { font-size: 23px; margin-bottom: 4px; } h2 { font-size: 15px; margin-top: 25px; } p { color: #445950; line-height: 1.5; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; } th, td { padding: 8px 5px; text-align: left; border-bottom: 1px solid #dbe4df; overflow-wrap: anywhere; }
th { background: #edf5f1; } tr { page-break-inside: avoid; } thead { display: table-header-group; } .money { text-align: right; }
</style></head><body>
<h1>Person statement</h1><h2>{{ $name }}</h2><p>Generated {{ $generated_at }}<br>Activity: {{ $from ?? 'All dates' }} — {{ $to ?? 'All dates' }}</p>
<h2>Current balances · all dates</h2><p>Only confirmed repayments reduce balances. Voided loans are excluded. Each currency is settled separately. These are current balances, not historical closing balances.</p>
<table><thead><tr><th>Currency / direction</th><th class="money">Principal</th><th class="money">Confirmed repaid</th><th class="money">Outstanding</th></tr></thead><tbody>
@forelse($balances as $balance)<tr><td>{{ $balance['currency'] }}<br>{{ $balance['direction'] === 'lent' ? 'To receive' : 'To pay' }}</td><td class="money">{{ $money($balance['amount_minor'], $balance['currency']) }}</td><td class="money">{{ $money($balance['repaid_minor'], $balance['currency']) }}</td><td class="money">{{ $money($balance['outstanding_minor'], $balance['currency']) }}</td></tr>@empty<tr><td colspan="4">No active balances.</td></tr>@endforelse
</tbody></table>
@foreach($rows->groupBy('currency')->sortKeys() as $currency => $entries)
<h2>{{ $currency }} activity</h2><table><thead><tr><th style="width: 18%">Date</th><th style="width: 47%">Record / description</th><th>Status</th><th class="money">Amount</th></tr></thead><tbody>
@foreach($entries as $row)<tr><td>{{ $row['date'] }}</td><td>{{ $row['type'] }} · #{{ $row['loan_id'] }}<br>{{ $row['direction'] === 'lent' ? 'Money lent' : 'Money borrowed' }}<br>{{ $row['description'] }}</td><td>{{ $row['status'] }}</td><td class="money">{{ $money($row['amount_minor'], $currency) }}</td></tr>@endforeach
</tbody></table>@endforeach
@if($rows->isEmpty())<p>No activity in this date range.</p>@endif
<p>Repayment entries show only the portion allocated to the visible loan. Pending, rejected and reversed repayments do not reduce balances. Private notes and contact emails are omitted. This statement reflects records in Ovezi and is not proof of payment.</p>
</body></html>
