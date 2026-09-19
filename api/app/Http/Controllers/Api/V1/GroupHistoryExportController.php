<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\GroupHistoryCsvExporter;
use App\Services\GroupHistoryPdfExporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class GroupHistoryExportController extends Controller
{
    public function __invoke(
        Request $request,
        Group $group,
        GroupHistoryCsvExporter $csvExporter,
        GroupHistoryPdfExporter $pdfExporter,
    ): Response {
        Gate::authorize('view', $group);

        $validated = $request->validate([
            'format' => ['nullable', Rule::in(['csv', 'pdf'])],
        ]);
        $format = $validated['format'] ?? 'csv';
        $filename = Str::slug($group->name).'-history.'.$format;

        if ($format === 'pdf') {
            return response($pdfExporter->export($group), Response::HTTP_OK, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        return response($csvExporter->export($group), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
