<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        RateLimiter::for('auth', function (Request $request) {
            $email = Str::lower($request->input('email', ''));

            return Limit::perMinute(5)->by($email . '|' . $request->ip());
        });

        // Guest login has no email to distinguish users, so keying it with the
        // `auth` limiter collapses every device on a shared network (e.g. a
        // restaurant's WiFi) into one 5/min bucket. There is nothing to
        // brute-force here, so allow a much higher per-IP rate instead.
        RateLimiter::for('guest', function (Request $request) {
            return Limit::perMinute(60)->by('guest:' . $request->ip());
        });

        RateLimiter::for('table-pin', function (Request $request) {
            $token = $request->input('token', '');

            return Limit::perMinute(5)->by('table-pin:' . $token . '|' . $request->ip());
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
