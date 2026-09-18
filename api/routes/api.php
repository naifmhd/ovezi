<?php

use App\Http\Controllers\Api\V1\Auth\RegistrationController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', RegistrationController::class)
            ->middleware('throttle:registration')
            ->name('register');
        Route::post('login', [SessionController::class, 'store'])
            ->middleware('throttle:login')
            ->name('login');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', ProfileController::class)->name('me');
        Route::delete('auth/session', [SessionController::class, 'destroy'])
            ->name('auth.session.destroy');
        Route::post('expenses', [ExpenseController::class, 'store'])
            ->name('expenses.store');
    });
});
