<?php

use App\Http\Controllers\Api\V1\AcceptGroupInviteController;
use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\ArchivedGroupController;
use App\Http\Controllers\Api\V1\Auth\AllSessionsController;
use App\Http\Controllers\Api\V1\Auth\DisconnectSocialAccountController;
use App\Http\Controllers\Api\V1\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Api\V1\Auth\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Auth\RegistrationController;
use App\Http\Controllers\Api\V1\Auth\ResetPasswordController;
use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\Auth\SocialLoginController;
use App\Http\Controllers\Api\V1\Auth\UpdateEmailController;
use App\Http\Controllers\Api\V1\Auth\UpdatePasswordController;
use App\Http\Controllers\Api\V1\Auth\VerifyEmailController;
use App\Http\Controllers\Api\V1\DeleteAccountController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\GroupActivityController;
use App\Http\Controllers\Api\V1\GroupBalanceController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\GroupCurrencyRateController;
use App\Http\Controllers\Api\V1\GroupInviteController;
use App\Http\Controllers\Api\V1\GroupMemberController;
use App\Http\Controllers\Api\V1\PlaceholderController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SettlementController;
use App\Http\Controllers\Api\V1\TransferGroupOwnershipController;
use App\Http\Controllers\Api\V1\UpdateProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', RegistrationController::class)
            ->middleware('throttle:registration')
            ->name('register');
        Route::post('login', [SessionController::class, 'store'])
            ->middleware('throttle:login')
            ->name('login');
        Route::post('social', SocialLoginController::class)
            ->middleware('throttle:login')
            ->name('social');
        Route::post('forgot-password', ForgotPasswordController::class)
            ->middleware('throttle:6,1')
            ->name('password.forgot');
        Route::post('reset-password', ResetPasswordController::class)
            ->middleware('throttle:6,1')
            ->name('password.reset');
        Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
            ->middleware(['signed', 'throttle:6,1'])
            ->name('email.verify');
    });

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', ProfileController::class)->name('me');
        Route::delete('auth/session', [SessionController::class, 'destroy'])
            ->name('auth.session.destroy');
        Route::delete('auth/sessions', AllSessionsController::class)
            ->name('auth.sessions.destroy');
        Route::put('auth/password', UpdatePasswordController::class)
            ->name('auth.password.update');
        Route::put('auth/email', UpdateEmailController::class)
            ->name('auth.email.update');
        Route::post('auth/email/verification-notification', EmailVerificationNotificationController::class)
            ->middleware('throttle:6,1')
            ->name('auth.email.verification-notification');
        Route::delete('auth/social-accounts/{provider}', DisconnectSocialAccountController::class)
            ->name('auth.social-accounts.destroy');
        Route::patch('me', UpdateProfileController::class)
            ->name('me.update');
        Route::delete('me', DeleteAccountController::class)
            ->name('me.destroy');
        Route::get('activity', [ActivityController::class, 'index'])
            ->name('activity.index');
        Route::apiResource('expenses', ExpenseController::class)
            ->only(['index', 'show', 'store', 'destroy']);
        Route::post('expenses/{expense}/restore', [ExpenseController::class, 'restore'])
            ->whereNumber('expense')
            ->name('expenses.restore');
        Route::apiResource('groups', GroupController::class)
            ->only(['index', 'store', 'show', 'update']);
        Route::post('groups/{group}/members', [GroupMemberController::class, 'store'])
            ->name('groups.members.store');
        Route::delete('groups/{group}/members/{member}', [GroupMemberController::class, 'destroy'])
            ->name('groups.members.destroy');
        Route::get('groups/{group}/balances', GroupBalanceController::class)
            ->name('groups.balances.show');
        Route::get('groups/{group}/activity', GroupActivityController::class)
            ->name('groups.activity.index');
        Route::put('groups/{group}/owner', TransferGroupOwnershipController::class)
            ->name('groups.owner.update');
        Route::get('groups/{group}/currency-rates', [GroupCurrencyRateController::class, 'index'])
            ->name('groups.currency-rates.index');
        Route::put('groups/{group}/currency-rates/{currency}', [GroupCurrencyRateController::class, 'update'])
            ->name('groups.currency-rates.update');
        Route::delete('groups/{group}/currency-rates/{currency}', [GroupCurrencyRateController::class, 'destroy'])
            ->name('groups.currency-rates.destroy');
        Route::post('groups/{group}/settlements', [SettlementController::class, 'store'])
            ->name('groups.settlements.store');
        Route::apiResource('groups.invites', GroupInviteController::class)
            ->only(['index', 'store', 'destroy']);
        Route::put('groups/{group}/archive', [ArchivedGroupController::class, 'store'])
            ->name('groups.archive.store');
        Route::delete('groups/{group}/archive', [ArchivedGroupController::class, 'destroy'])
            ->name('groups.archive.destroy');
        Route::apiResource('placeholders', PlaceholderController::class)
            ->only(['index', 'store', 'update']);
        Route::post('group-invites/accept', AcceptGroupInviteController::class)
            ->name('group-invites.accept');
    });
});
