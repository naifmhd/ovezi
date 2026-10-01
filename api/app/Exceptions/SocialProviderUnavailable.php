<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class SocialProviderUnavailable extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json(['message' => 'Apple sign-in is temporarily unavailable. Please try again later or use another sign-in method.'], 503);
    }
}
