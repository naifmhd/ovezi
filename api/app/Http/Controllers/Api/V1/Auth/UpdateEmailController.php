<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\UpdateEmail;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\UpdateEmailRequest;
use App\Http\Resources\Api\V1\UserResource;

class UpdateEmailController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UpdateEmailRequest $request, UpdateEmail $updateEmail): UserResource
    {
        return UserResource::make($updateEmail->execute(
            $request->user(),
            $request->string('current_password')->toString(),
            $request->string('email')->toString(),
        ));
    }
}
