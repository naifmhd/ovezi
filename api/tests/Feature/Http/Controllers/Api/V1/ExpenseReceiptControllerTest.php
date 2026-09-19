<?php

use App\ExpenseType;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** @return array{expense: Expense, owner: User} */
function receiptExpense(): array
{
    $currency = Currency::factory()->mvr()->create();
    $owner = User::factory()->create(['default_currency_code' => $currency->code]);
    $expense = Expense::factory()->create([
        'expense_type' => ExpenseType::Personal,
        'group_id' => null,
        'payer_user_id' => $owner->id,
        'currency_code' => $currency->code,
        'reporting_currency_code' => $currency->code,
        'created_by' => $owner->id,
    ]);

    return compact('expense', 'owner');
}

it('stores a private receipt and allows only authorized viewing', function () {
    ['expense' => $expense, 'owner' => $owner] = receiptExpense();
    $outsider = User::factory()->create();
    Storage::fake('receipts');
    config(['filesystems.receipts_disk' => 'receipts']);

    $response = $this->actingAs($owner)->postJson("/api/v1/expenses/{$expense->id}/receipt", [
        'receipt' => UploadedFile::fake()->image('receipt.jpg', 1200, 1600),
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.has_receipt', true)
        ->assertJsonPath('data.receipt_url', route('api.v1.expenses.receipt.show', $expense))
        ->assertJsonPath('data.payer.user_id', $owner->id)
        ->assertJsonPath('data.splits', []);
    $path = $expense->fresh()->receipt_image_path;
    expect($path)->not->toBeNull();
    Storage::disk('receipts')->assertExists($path);
    $this->assertDatabaseHas('activity_logs', [
        'actor_id' => $owner->id,
        'subject_id' => $expense->id,
        'event' => 'expense.updated',
    ]);

    $receiptResponse = $this->actingAs($owner)
        ->get("/api/v1/expenses/{$expense->id}/receipt")
        ->assertOk();
    expect($receiptResponse->headers->get('cache-control'))
        ->toContain('private')
        ->toContain('max-age=3600');
    $this->actingAs($outsider)
        ->get("/api/v1/expenses/{$expense->id}/receipt")
        ->assertForbidden();
});

it('replaces and removes a receipt without leaving old files behind', function () {
    ['expense' => $expense, 'owner' => $owner] = receiptExpense();
    Storage::fake('receipts');
    config(['filesystems.receipts_disk' => 'receipts']);
    $firstPath = 'receipts/old-receipt.jpg';
    Storage::disk('receipts')->put($firstPath, 'old receipt');
    $expense->update(['receipt_image_path' => $firstPath]);

    $this->actingAs($owner)->postJson("/api/v1/expenses/{$expense->id}/receipt", [
        'receipt' => UploadedFile::fake()->image('replacement.png'),
    ])->assertOk();

    $replacementPath = $expense->fresh()->receipt_image_path;
    Storage::disk('receipts')->assertMissing($firstPath);
    Storage::disk('receipts')->assertExists($replacementPath);

    $this->actingAs($owner)
        ->deleteJson("/api/v1/expenses/{$expense->id}/receipt")
        ->assertNoContent();
    expect($expense->fresh()->receipt_image_path)->toBeNull();
    Storage::disk('receipts')->assertMissing($replacementPath);
});

it('rejects non-image receipt uploads', function () {
    ['expense' => $expense, 'owner' => $owner] = receiptExpense();
    Storage::fake('receipts');
    config(['filesystems.receipts_disk' => 'receipts']);

    $this->actingAs($owner)->postJson("/api/v1/expenses/{$expense->id}/receipt", [
        'receipt' => UploadedFile::fake()->create('receipt.txt', 10, 'text/plain'),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('receipt');

    expect($expense->fresh()->receipt_image_path)->toBeNull();
    Storage::disk('receipts')->assertDirectoryEmpty('/');
});

it('rejects receipt images larger than ten megabytes', function () {
    ['expense' => $expense, 'owner' => $owner] = receiptExpense();
    Storage::fake('receipts');
    config(['filesystems.receipts_disk' => 'receipts']);

    $this->actingAs($owner)->postJson("/api/v1/expenses/{$expense->id}/receipt", [
        'receipt' => UploadedFile::fake()->image('receipt.jpg')->size(10_241),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('receipt');

    expect($expense->fresh()->receipt_image_path)->toBeNull();
    Storage::disk('receipts')->assertDirectoryEmpty('/');
});

it('forbids receipt changes by someone who cannot edit the expense', function () {
    ['expense' => $expense] = receiptExpense();
    $outsider = User::factory()->create();
    Storage::fake('receipts');
    config(['filesystems.receipts_disk' => 'receipts']);

    $this->actingAs($outsider)->postJson("/api/v1/expenses/{$expense->id}/receipt", [
        'receipt' => UploadedFile::fake()->image('receipt.jpg'),
    ])->assertForbidden();

    expect($expense->fresh()->receipt_image_path)->toBeNull();
    Storage::disk('receipts')->assertDirectoryEmpty('/');
});
