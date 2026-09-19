<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateDirectSettlement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDirectSettlementRequest;
use App\Http\Resources\Api\V1\SettlementResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class DirectSettlementController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        StoreDirectSettlementRequest $request,
        CreateDirectSettlement $createDirectSettlement,
    ): JsonResponse {
        $data = $request->safe()->only([
            'from_user_id', 'from_placeholder_id', 'to_user_id', 'to_placeholder_id',
            'amount_minor', 'currency_code', 'reporting_currency_code', 'method', 'note', 'occurred_at',
        ]);
        $data['occurred_at'] = CarbonImmutable::parse($data['occurred_at']);
        $settlement = $createDirectSettlement->execute($request->user(), $data);

        return SettlementResource::make($settlement)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
