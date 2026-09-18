<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\UpdatePassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\UpdatePasswordRequest;
use Illuminate\Http\Response;

class UpdatePasswordController extends Controller
{
    public function __invoke(
        UpdatePasswordRequest $request,
        UpdatePassword $updatePassword,
    ): Response {
        $updatePassword->execute(
            $request->user(),
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
        );

        return response()->noContent();
    }
}
