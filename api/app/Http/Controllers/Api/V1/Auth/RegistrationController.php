<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RegistrationController extends Controller
{
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = User::create($request->safe()->only([
            'name',
            'email',
            'password',
        ]));

        event(new Registered($user));

        $token = $user->createToken($request->string('device_name')->toString());

        return response()->json([
            'data' => [
                'user' => UserResource::make($user),
                'token' => $token->plainTextToken,
            ],
        ], Response::HTTP_CREATED);
    }
}
