<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 34px 38px 42px; }
        * { box-sizing: border-box; }
        body { color: #0A1128; font-family: "DejaVu Sans", sans-serif; font-size: 9px; margin: 0; }
        .header { border-bottom: 2px solid #00C984; margin-bottom: 18px; padding-bottom: 12px; }
        .brand { color: #00A86F; font-size: 11px; font-weight: bold; letter-spacing: 1.5px; text-transform: uppercase; }
        h1 { font-size: 24px; line-height: 1.2; margin: 4px 0; }
        .meta { color: #5C6578; font-size: 8px; }
        .summary { background: #F1FBF7; border: 1px solid #D6EFE5; border-radius: 7px; margin-bottom: 14px; padding: 9px 11px; }
        .summary strong { color: #087A56; }
        table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #0A1128; color: #FFFFFF; font-size: 7px; letter-spacing: .35px; padding: 6px; text-align: left; text-transform: uppercase; }
        td { border-bottom: 1px solid #E1E7E5; line-height: 1.3; overflow-wrap: break-word; padding: 5px 6px; vertical-align: top; }
        tr:nth-child(even) td { background: #F8FAF9; }
        .status-deleted { color: #C63E4E; font-weight: bold; }
        .amount { font-weight: bold; white-space: nowrap; }
        .muted { color: #5C6578; }
        .empty { color: #5C6578; font-size: 12px; padding: 48px 0; text-align: center; }
        .footer { bottom: -27px; color: #7A8498; font-size: 7px; left: 0; position: fixed; right: 0; text-align: center; }
        .c-date { width: 9%; } .c-type { width: 8%; } .c-status { width: 7%; }
        .c-description { width: 19%; } .c-from { width: 12%; } .c-to { width: 18%; }
        .c-amount { width: 10%; } .c-reporting { width: 10%; } .c-detail { width: 7%; }
    </style>
</head>
<body>
    <div class="footer">Ovezi - Split. Share. Settle.</div>
    <div class="header">
        <div class="brand">Ovezi</div>
        <h1>{{ $group->name }} history</h1>
        <div class="meta">Generated {{ $generatedAt->format('d M Y, H:i T') }}</div>
    </div>
    <div class="summary">
        Reporting currency: <strong>{{ $group->reporting_currency_code }}</strong>
        &nbsp;&nbsp; Records: <strong>{{ $records->count() }}</strong>
    </div>
    @if ($records->isEmpty())
        <div class="empty">No expenses or settlements have been recorded for this group.</div>
    @else
        <table>
            <thead>
                <tr>
                    <th class="c-date">Date</th>
                    <th class="c-type">Type</th>
                    <th class="c-status">Status</th>
                    <th class="c-description">Description</th>
                    <th class="c-from">Payer / From</th>
                    <th class="c-to">Participants / To</th>
                    <th class="c-amount">Amount</th>
                    <th class="c-reporting">Reporting</th>
                    <th class="c-detail">Detail</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($records as $record)
                    <tr>
                        <td>{{ $record['date'] }}</td>
                        <td>{{ $record['type'] }}</td>
                        <td class="{{ $record['status'] === 'Deleted' ? 'status-deleted' : 'muted' }}">{{ $record['status'] }}</td>
                        <td>{{ $record['description'] }}</td>
                        <td>{{ $record['from'] }}</td>
                        <td>{{ $record['to'] }}</td>
                        <td class="amount">{{ $record['amount'] }}</td>
                        <td class="amount">{{ $record['reporting_amount'] }}</td>
                        <td class="muted">{{ $record['detail'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
