<?php

namespace App\Providers;

use App\Services\CustomerCommandBus;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        $this->configureCommandWorkerHeartbeat();
    }

    /**
     * Let a queue worker consuming the customer command queue advertise that it
     * is alive. `Looping` fires on every daemon poll (even while idle), so the
     * heartbeat stays fresh whenever a worker is up; `JobProcessed` keeps it
     * fresh during busy bursts. The producer side checks this heartbeat before
     * enqueuing cart writes and falls back to synchronous processing when no
     * worker is draining the queue — preventing silently lost cart items.
     */
    protected function configureCommandWorkerHeartbeat(): void
    {
        $configuredQueue = (string) config('services.customer_commands.queue', 'customercommands');

        $touch = function (?string $eventQueue) use ($configuredQueue): void {
            if ($eventQueue === null) {
                return;
            }

            // A worker may listen to a comma-separated list of queues.
            $queues = array_map('trim', explode(',', $eventQueue));
            if (! in_array($configuredQueue, $queues, true)) {
                return;
            }

            app(CustomerCommandBus::class)->heartbeat();
        };

        Event::listen(Looping::class, fn (Looping $event) => $touch($event->queue));
        Event::listen(JobProcessed::class, fn (JobProcessed $event) => $touch($event->job->getQueue()));
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
