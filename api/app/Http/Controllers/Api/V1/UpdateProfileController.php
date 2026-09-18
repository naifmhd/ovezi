<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\UpdateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;

class UpdateProfileController extends Controller
{
    public function __invoke(UpdateProfileRequest $request, UpdateProfile $updateProfile): UserResource
    {
        return UserResource::make(
            $updateProfile->execute(
                $request->user(),
                $request->safe()->only(['name', 'default_currency_code']),
            ),
        );
    }
}
