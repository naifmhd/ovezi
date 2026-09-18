<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\CreateSettlement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreSettlementRequest;
use App\Http\Resources\Api\V1\SettlementResource;
use App\Models\Group;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class SettlementController extends Controller
{
    public function store(
        StoreSettlementRequest $request,
        Group $group,
        CreateSettlement $createSettlement,
    ): JsonResponse {
        $data = $request->safe()->only([
            'from_user_id', 'from_placeholder_id', 'to_user_id', 'to_placeholder_id',
            'amount_minor', 'currency_code', 'method', 'note', 'occurred_at',
        ]);
        $data['occurred_at'] = CarbonImmutable::parse($data['occurred_at']);
        $settlement = $createSettlement->execute($request->user(), $group, $data);

        return SettlementResource::make($settlement)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
