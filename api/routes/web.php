<?php

use App\Http\Controllers\AccountDeletionController;
use App\Http\Controllers\AppLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
})->name('home');

Route::view('/privacy', 'privacy')->name('privacy');
Route::view('/terms', 'terms')->name('terms');
Route::view('/support', 'support')->name('support');

Route::middleware(['throttle:10,1'])->group(function (): void {
    Route::get('/delete-account', [AccountDeletionController::class, 'create'])->name('account-deletion.create');
    Route::post('/delete-account', [AccountDeletionController::class, 'store'])->name('account-deletion.store');
    Route::get('/delete-account/confirm/{token}', [AccountDeletionController::class, 'show'])->name('account-deletion.show');
    Route::post('/delete-account/confirm/{token}', [AccountDeletionController::class, 'destroy'])->name('account-deletion.destroy');
});

Route::get('/group-invites/accept', [AppLinkController::class, 'show'])->name('app.invite');
Route::get('/auth/reset-password', [AppLinkController::class, 'show'])->name('app.reset');
Route::get('/auth/verify-email', [AppLinkController::class, 'show'])->name('app.verify');
Route::get('/.well-known/apple-app-site-association', [AppLinkController::class, 'apple']);
Route::get('/.well-known/assetlinks.json', [AppLinkController::class, 'android']);
