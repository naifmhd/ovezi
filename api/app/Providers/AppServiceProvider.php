<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Settlement;
use App\Models\User;
use App\Observers\DomainChangeObserver;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Expense::observe(DomainChangeObserver::class);
        Settlement::observe(DomainChangeObserver::class);
        Group::observe(DomainChangeObserver::class);
        GroupMember::observe(DomainChangeObserver::class);

        VerifyEmail::createUrlUsing(function (User $user): string {
            $verificationUrl = URL::temporarySignedRoute(
                'api.v1.auth.email.verify',
                now()->addMinutes(60),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ],
            );

            return sprintf(
                '%s://auth/verify-email?verification_url=%s',
                config('ovezi.deep_link_scheme'),
                rawurlencode($verificationUrl),
            );
        });

        ResetPassword::createUrlUsing(fn (User $user, string $token): string => sprintf(
            '%s://auth/reset-password?token=%s&email=%s',
            config('ovezi.deep_link_scheme'),
            rawurlencode($token),
            rawurlencode($user->getEmailForPasswordReset()),
        ));

        RateLimiter::for('registration', function (Request $request): Limit {
            return Limit::perMinute(6)->by($this->rateLimitKey($request));
        });

        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by($this->rateLimitKey($request));
        });
    }

    private function rateLimitKey(Request $request): string
    {
        return Str::transliterate(
            Str::lower($request->string('email')->toString()).'|'.$request->ip(),
        );
    }
}
