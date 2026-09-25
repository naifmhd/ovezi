<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\GroupCurrencyRate;
use App\Models\GroupInvite;
use App\Models\GroupMember;
use App\Models\Placeholder;
use App\Models\RecurringExpense;
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
        Friendship::observe(DomainChangeObserver::class);
        GroupInvite::observe(DomainChangeObserver::class);
        Placeholder::observe(DomainChangeObserver::class);
        GroupCurrencyRate::observe(DomainChangeObserver::class);
        RecurringExpense::observe(DomainChangeObserver::class);

        VerifyEmail::createUrlUsing(function (User $user): string {
            $verificationUrl = URL::temporarySignedRoute(
                'api.v1.auth.email.verify',
                now()->addMinutes(60),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ],
            );

            return route('app.verify', ['verification_url' => $verificationUrl]);
        });

        ResetPassword::createUrlUsing(fn (User $user, string $token): string => route('app.reset', [
            'token' => $token, 'email' => $user->getEmailForPasswordReset(),
        ]));

        foreach (['uploads' => 20, 'exports' => 6, 'invitations' => 10, 'account-security' => 6] as $name => $attempts) {
            RateLimiter::for($name, fn (Request $request): Limit => Limit::perMinute($attempts)->by((string) ($request->user()?->id ?? $request->ip())));
        }

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
