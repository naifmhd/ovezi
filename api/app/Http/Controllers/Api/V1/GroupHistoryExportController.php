<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Services\GroupHistoryCsvExporter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GroupHistoryExportController extends Controller
{
    public function __invoke(Group $group, GroupHistoryCsvExporter $exporter): Response
    {
        Gate::authorize('view', $group);

        $filename = Str::slug($group->name).'-history.csv';

        return response($exporter->export($group), Response::HTTP_OK, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
