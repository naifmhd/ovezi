<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LendingLedger;
use App\Services\LendingStatementExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class LendingStatementController extends Controller
{
    public function __invoke(Request $request, int $contact, LendingLedger $ledger, LendingStatementExporter $exporter): Response
    {
        $person = $ledger->contact($request->user(), $contact)->load('owner:id,name');
        $data = $request->validate([
            'format' => ['required', Rule::in(['csv', 'pdf'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $statement = $exporter->statement($request->user(), $person, $data['from'] ?? null, $data['to'] ?? null);
        $format = $data['format'];
        return response($format === 'pdf' ? $exporter->pdf($statement) : $exporter->csv($statement), 200, [
            'Content-Type' => $format === 'pdf' ? 'application/pdf' : 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.(Str::slug($statement['name']) ?: 'ovezi').'-statement.'.$format.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
