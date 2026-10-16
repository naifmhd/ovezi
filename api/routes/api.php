<?php

use App\Http\Controllers\Api\V1\AcceptFriendRequestController;
use App\Http\Controllers\Api\V1\AcceptGroupInviteController;
use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\ArchivedGroupController;
use App\Http\Controllers\Api\V1\Auth\AllSessionsController;
use App\Http\Controllers\Api\V1\Auth\ConnectSocialAccountController;
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
use App\Http\Controllers\Api\V1\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\ExpenseReceiptController;
use App\Http\Controllers\Api\V1\FriendshipController;
use App\Http\Controllers\Api\V1\GroupActivityController;
use App\Http\Controllers\Api\V1\GroupBalanceController;
use App\Http\Controllers\Api\V1\GroupCleanupController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\GroupCurrencyRateController;
use App\Http\Controllers\Api\V1\GroupHistoryExportController;
use App\Http\Controllers\Api\V1\GroupInviteController;
use App\Http\Controllers\Api\V1\GroupMemberController;
use App\Http\Controllers\Api\V1\GroupNotificationMuteController;
use App\Http\Controllers\Api\V1\GroupPhotoController;
use App\Http\Controllers\Api\V1\LendingBalanceController;
use App\Http\Controllers\Api\V1\LendingContactController;
use App\Http\Controllers\Api\V1\LendingDuplicateController;
use App\Http\Controllers\Api\V1\LendingInvitationController;
use App\Http\Controllers\Api\V1\LendingLoanController;
use App\Http\Controllers\Api\V1\LendingOverviewController;
use App\Http\Controllers\Api\V1\LendingReminderController;
use App\Http\Controllers\Api\V1\LendingRepaymentController;
use App\Http\Controllers\Api\V1\LendingStatementController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\OverallBalanceController;
use App\Http\Controllers\Api\V1\PersonalDataExportController;
use App\Http\Controllers\Api\V1\PinController;
use App\Http\Controllers\Api\V1\PlaceholderClaimController;
use App\Http\Controllers\Api\V1\PlaceholderController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\PushTokenController;
use App\Http\Controllers\Api\V1\RecurringExpenseController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SettlementController;
use App\Http\Controllers\Api\V1\TransferGroupOwnershipController;
use App\Http\Controllers\Api\V1\UpdateProfileController;
use App\Http\Middleware\IdempotentFinancialWrite;
use App\Http\Middleware\LockGroupWrite;
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

    Route::middleware(['auth:sanctum', IdempotentFinancialWrite::class, LockGroupWrite::class])->group(function (): void {
        Route::get('expense-categories', [ExpenseCategoryController::class, 'index']);
        Route::post('expense-categories', [ExpenseCategoryController::class, 'store']);
        Route::patch('expense-categories/{category}', [ExpenseCategoryController::class, 'update'])->whereNumber('category');
        Route::put('groups/{group}/pin', [PinController::class, 'group'])->name('groups.pin');
        Route::put('lending/contacts/{contact}/pin', [PinController::class, 'contact'])->whereNumber('contact')->name('lending.contacts.pin');
        Route::prefix('lending')->name('lending.')->group(function (): void {
            Route::get('contacts/{contact}/statement', LendingStatementController::class)->whereNumber('contact')->middleware('throttle:exports')->name('statement');
            Route::get('loans/{loan}/reminder', [LendingReminderController::class, 'show'])->whereNumber('loan');
            Route::put('loans/{loan}/reminder', [LendingReminderController::class, 'update'])->whereNumber('loan');
            Route::delete('loans/{loan}/reminder', [LendingReminderController::class, 'destroy'])->whereNumber('loan');
            Route::get('contacts/{contact}/balance', LendingBalanceController::class)->whereNumber('contact')->name('balance');
            Route::post('loans/duplicates', LendingDuplicateController::class)->name('loans.duplicates');
            Route::get('overview', LendingOverviewController::class);
            Route::post('loans', [LendingLoanController::class, 'quickStore'])->name('loans.quick-store');
            Route::get('attention', [LendingOverviewController::class, 'attention']);
            Route::get('contacts', [LendingContactController::class, 'index'])->name('contacts.index');
            Route::post('contacts', [LendingContactController::class, 'store'])->name('contacts.store');
            Route::get('contacts/{contact}', [LendingContactController::class, 'show'])->whereNumber('contact');
            Route::patch('contacts/{contact}', [LendingContactController::class, 'update'])->whereNumber('contact');
            Route::post('contacts/{contact}/disconnect', [LendingContactController::class, 'disconnect'])->whereNumber('contact')->name('disconnect');
            Route::get('contacts/{contact}/loans', [LendingLoanController::class, 'index'])->whereNumber('contact');
            Route::post('contacts/{contact}/loans', [LendingLoanController::class, 'store'])->whereNumber('contact')->name('loans.store');
            Route::get('loans/{loan}', [LendingLoanController::class, 'show'])->whereNumber('loan');
            Route::get('loans/{loan}/history', [LendingLoanController::class, 'history'])->whereNumber('loan');
            Route::post('loans/{loan}/actions', [LendingLoanController::class, 'update'])->whereNumber('loan')->name('loans.actions');
            Route::post('contacts/{contact}/repayments', [LendingRepaymentController::class, 'store'])->whereNumber('contact')->name('repayments.store');
            Route::post('repayments/{repayment}/actions', [LendingRepaymentController::class, 'update'])->whereNumber('repayment')->name('repayments.actions');
            Route::get('invitations', [LendingInvitationController::class, 'index']);
            Route::post('contacts/{contact}/share', [LendingInvitationController::class, 'store'])->whereNumber('contact')->middleware('throttle:invitations')->name('share');
            Route::post('invitations/{contact}/actions', [LendingInvitationController::class, 'update'])->whereNumber('contact')->middleware('throttle:invitations')->name('invitations.actions');
        });
        Route::get('me', ProfileController::class)->name('me');
        Route::get('me/export', PersonalDataExportController::class)->middleware('throttle:exports')->name('me.export');
        Route::get('currencies', CurrencyController::class)->name('currencies.index');
        Route::delete('auth/session', [SessionController::class, 'destroy'])
            ->name('auth.session.destroy');
        Route::delete('auth/sessions', AllSessionsController::class)
            ->name('auth.sessions.destroy');
        Route::put('auth/password', UpdatePasswordController::class)
            ->middleware('throttle:account-security')->name('auth.password.update');
        Route::put('auth/email', UpdateEmailController::class)
            ->middleware('throttle:account-security')->name('auth.email.update');
        Route::post('auth/email/verification-notification', EmailVerificationNotificationController::class)
            ->middleware('throttle:6,1')
            ->name('auth.email.verification-notification');
        Route::post('auth/social-accounts', ConnectSocialAccountController::class)
            ->middleware('throttle:account-security')->name('auth.social-accounts.store');
        Route::delete('auth/social-accounts/{provider}', DisconnectSocialAccountController::class)
            ->name('auth.social-accounts.destroy');
        Route::patch('me', UpdateProfileController::class)
            ->name('me.update');
        Route::delete('me', DeleteAccountController::class)
            ->middleware('throttle:account-security')->name('me.destroy');
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
        Route::apiResource('recurring-expenses', RecurringExpenseController::class)
            ->parameters(['recurring-expenses' => 'recurringExpense'])
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::put('recurring-expenses/{recurringExpense}/pause', [RecurringExpenseController::class, 'pause'])
            ->name('recurring-expenses.pause');
        Route::delete('recurring-expenses/{recurringExpense}/pause', [RecurringExpenseController::class, 'resume'])
            ->name('recurring-expenses.resume');
        Route::post('expenses/{expense}/restore', [ExpenseController::class, 'restore'])
            ->whereNumber('expense')
            ->name('expenses.restore');
        Route::get('expenses/{expense}/receipt', [ExpenseReceiptController::class, 'show'])
            ->name('expenses.receipt.show');
        Route::post('expenses/{expense}/receipt', [ExpenseReceiptController::class, 'store'])
            ->middleware('throttle:uploads')->name('expenses.receipt.store');
        Route::delete('expenses/{expense}/receipt', [ExpenseReceiptController::class, 'destroy'])
            ->name('expenses.receipt.destroy');
        Route::apiResource('groups', GroupController::class)
            ->only(['index', 'store', 'show', 'update']);
        Route::delete('groups/{group}', [GroupCleanupController::class, 'destroy'])->name('groups.destroy');
        Route::post('groups/{group}/restore', [GroupCleanupController::class, 'restore'])->whereNumber('group')->name('groups.restore');
        Route::put('groups/{group}/hidden', [GroupCleanupController::class, 'hidden'])->name('groups.hidden');
        Route::get('groups/{group}/photo', [GroupPhotoController::class, 'show'])
            ->name('groups.photo.show');
        Route::post('groups/{group}/photo', [GroupPhotoController::class, 'store'])
            ->middleware('throttle:uploads')->name('groups.photo.store');
        Route::delete('groups/{group}/photo', [GroupPhotoController::class, 'destroy'])
            ->name('groups.photo.destroy');
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
            ->middleware('throttle:exports')->name('groups.export');
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
            ->only(['index', 'store', 'destroy'])->middlewareFor('store', 'throttle:invitations');
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
