<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\DeleteExpenseReceipt;
use App\Actions\StoreExpenseReceipt;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreExpenseReceiptRequest;
use App\Http\Resources\Api\V1\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseReceiptController extends Controller
{
    public function show(Expense $expense): StreamedResponse
    {
        Gate::authorize('view', $expense);
        abort_if($expense->receipt_image_path === null, 404);

        $disk = Storage::disk((string) config('filesystems.receipts_disk'));
        abort_unless($disk->exists($expense->receipt_image_path), 404);

        return $disk->response($expense->receipt_image_path, null, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    public function store(
        StoreExpenseReceiptRequest $request,
        Expense $expense,
        StoreExpenseReceipt $storeExpenseReceipt,
    ): ExpenseResource {
        $updatedExpense = $storeExpenseReceipt->execute(
            $request->user(),
            $expense,
            $request->file('receipt'),
        );

        return ExpenseResource::make($updatedExpense->load([
            'payerUser:id,name',
            'payerPlaceholder:id,name,claimed_by',
            'payerPlaceholder.claimedBy:id,name',
            'splits.user:id,name',
            'splits.placeholder:id,name,claimed_by',
            'splits.placeholder.claimedBy:id,name',
        ]));
    }

    public function destroy(
        Request $request,
        Expense $expense,
        DeleteExpenseReceipt $deleteExpenseReceipt,
    ): Response {
        Gate::authorize('update', $expense);
        $deleteExpenseReceipt->execute($request->user(), $expense);

        return response()->noContent();
    }
}
