<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PersonalDataExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PersonalDataExportController extends Controller
{
    public function __invoke(Request $request, PersonalDataExporter $exporter): JsonResponse
    {
        $filename = 'ovezi-personal-data-'.now()->toDateString().'.json';

        return response()->json(
            $exporter->export($request->user()),
            Response::HTTP_OK,
            ['Content-Disposition' => 'attachment; filename="'.$filename.'"'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
}
