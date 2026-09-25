<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ConfirmAccountDeletion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeleteAccountRequest;
use Illuminate\Http\Response;

class DeleteAccountController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(DeleteAccountRequest $request, ConfirmAccountDeletion $deleteAccount): Response
    {
        $deleteAccount->execute(
            $request->user(),
            $request->validated(),
        );

        return response()->noContent();
    }
}
