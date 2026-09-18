<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DeleteGroupCurrencyRate;
use App\Actions\UpsertGroupCurrencyRate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DestroyGroupCurrencyRateRequest;
use App\Http\Requests\Api\V1\UpsertGroupCurrencyRateRequest;
use App\Http\Resources\Api\V1\GroupCurrencyRateResource;
use App\Models\Currency;
use App\Models\Group;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class GroupCurrencyRateController extends Controller
{
    public function index(Group $group): AnonymousResourceCollection
    {
        Gate::authorize('view', $group);

        return GroupCurrencyRateResource::collection(
            $group->currencyRates()->orderBy('base_currency_code')->get(),
        );
    }

    public function update(
        UpsertGroupCurrencyRateRequest $request,
        Group $group,
        Currency $currency,
        UpsertGroupCurrencyRate $upsertGroupCurrencyRate,
    ): GroupCurrencyRateResource {
        return GroupCurrencyRateResource::make(
            $upsertGroupCurrencyRate->execute(
                $request->user(),
                $group,
                $currency,
                $request->validated('rate'),
            ),
        );
    }

    public function destroy(
        DestroyGroupCurrencyRateRequest $request,
        Group $group,
        Currency $currency,
        DeleteGroupCurrencyRate $deleteGroupCurrencyRate,
    ): Response {
        $deleteGroupCurrencyRate->execute($request->user(), $group, $currency);

        return response()->noContent();
    }
}
