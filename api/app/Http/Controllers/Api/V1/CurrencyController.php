<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CurrencyResource;
use App\Models\Currency;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CurrencyController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return CurrencyResource::collection(
            Currency::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(),
        );
    }
}
