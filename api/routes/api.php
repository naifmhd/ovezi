<?php

use App\Http\Controllers\Api\V1\AcceptFriendRequestController;
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
use App\Http\Controllers\Api\V1\CurrencyController;
use App\Http\Controllers\Api\V1\DeleteAccountController;
use App\Http\Controllers\Api\V1\DirectSettlementController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\FriendshipController;
use App\Http\Controllers\Api\V1\GroupActivityController;
use App\Http\Controllers\Api\V1\GroupBalanceController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\GroupCurrencyRateController;
use App\Http\Controllers\Api\V1\GroupHistoryExportController;
use App\Http\Controllers\Api\V1\GroupInviteController;
use App\Http\Controllers\Api\V1\GroupMemberController;
use App\Http\Controllers\Api\V1\GroupNotificationMuteController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\OverallBalanceController;
use App\Http\Controllers\Api\V1\PersonalDataExportController;
use App\Http\Controllers\Api\V1\PlaceholderClaimController;
use App\Http\Controllers\Api\V1\PlaceholderController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PushTokenController;
use App\Http\Controllers\Api\V1\SearchController;
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
        Route::get('me/export', PersonalDataExportController::class)->name('me.export');
        Route::get('currencies', CurrencyController::class)->name('currencies.index');
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
        Route::get('notification-preferences', [NotificationPreferenceController::class, 'show'])
            ->name('notification-preferences.show');
        Route::patch('notification-preferences', [NotificationPreferenceController::class, 'update'])
            ->name('notification-preferences.update');
        Route::post('push-tokens', [PushTokenController::class, 'store'])
            ->name('push-tokens.store');
        Route::delete('push-tokens', [PushTokenController::class, 'destroy'])
            ->name('push-tokens.destroy');
        Route::get('activity', [ActivityController::class, 'index'])
            ->name('activity.index');
        Route::get('balances', OverallBalanceController::class)
            ->name('balances.show');
        Route::get('search', SearchController::class)->name('search');
        Route::apiResource('friends', FriendshipController::class)
            ->only(['index', 'store', 'destroy']);
        Route::post('friends/{friendship}/accept', AcceptFriendRequestController::class)
            ->name('friends.accept');
        Route::apiResource('expenses', ExpenseController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy']);
        Route::post('expenses/{expense}/restore', [ExpenseController::class, 'restore'])
            ->whereNumber('expense')
            ->name('expenses.restore');
        Route::apiResource('groups', GroupController::class)
            ->only(['index', 'store', 'show', 'update']);
        Route::post('groups/{group}/members', [GroupMemberController::class, 'store'])
            ->name('groups.members.store');
        Route::delete('groups/{group}/members/{member}', [GroupMemberController::class, 'destroy'])
            ->name('groups.members.destroy');
        Route::put('groups/{group}/notification-mute', [GroupNotificationMuteController::class, 'store'])
            ->name('groups.notification-mute.store');
        Route::delete('groups/{group}/notification-mute', [GroupNotificationMuteController::class, 'destroy'])
            ->name('groups.notification-mute.destroy');
        Route::get('groups/{group}/balances', GroupBalanceController::class)
            ->name('groups.balances.show');
        Route::get('groups/{group}/activity', GroupActivityController::class)
            ->name('groups.activity.index');
        Route::get('groups/{group}/export', GroupHistoryExportController::class)
            ->name('groups.export');
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
        Route::post('settlements', DirectSettlementController::class)
            ->name('settlements.store');
        Route::apiResource('groups.invites', GroupInviteController::class)
            ->only(['index', 'store', 'destroy']);
        Route::put('groups/{group}/archive', [ArchivedGroupController::class, 'store'])
            ->name('groups.archive.store');
        Route::delete('groups/{group}/archive', [ArchivedGroupController::class, 'destroy'])
            ->name('groups.archive.destroy');
        Route::apiResource('placeholders', PlaceholderController::class)
            ->only(['index', 'store', 'update']);
        Route::get('placeholder-claims', [PlaceholderClaimController::class, 'index'])
            ->name('placeholder-claims.index');
        Route::post('placeholder-claims/{placeholder}', [PlaceholderClaimController::class, 'store'])
            ->name('placeholder-claims.store');
        Route::post('group-invites/accept', AcceptGroupInviteController::class)
            ->name('group-invites.accept');
    });
});
