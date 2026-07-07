<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Brute-force guard on login. First limit (5/min per email+ip) caps
        // guesses against any single account without letting one shared NAT (a
        // classroom or home) lock out siblings. Second limit (20/min per ip)
        // blunts password spraying — one password tried across many accounts
        // from one ip. Note: the throttle middleware counts every attempt,
        // including a successful one, and does not clear on success; a
        // deliberate trade-off for staying with the simple middleware approach.
        RateLimiter::for('auth-login', function (Request $request) {
            $raw = $request->input('email');
            $email = is_string($raw) ? mb_strtolower(trim($raw)) : '';

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),
                Limit::perMinute(20)->by('login-ip|'.$request->ip()),
            ];
        });

        // Registration guard, keyed by ip (the email varies per fake account).
        // Tuned for batch onboarding — a teacher registering a class of up to
        // ~30 from one school ip: 40/min absorbs a full class with headroom for
        // retries, while the 120/hour tail still blocks sustained spam.
        RateLimiter::for('auth-register', function (Request $request) {
            return [
                Limit::perMinute(40)->by((string) $request->ip()),
                Limit::perHour(120)->by('register-hourly|'.$request->ip()),
            ];
        });

        // Google OAuth callback also creates accounts, so it gets its own per-ip
        // budget — a separate name means it doesn't share auth-register's bucket,
        // so a class registering by email and OAuth logins from the same school
        // ip don't compete for one limit. No hourly tail (unlike auth-register):
        // an invalid callback fails before the external Google call, so it isn't
        // a sustained-spam vector worth the extra limit.
        RateLimiter::for('auth-oauth-callback', function (Request $request) {
            return Limit::perMinute(40)->by('oauth-cb|'.$request->ip());
        });
    }
}
