<?php

namespace App\Providers;

use App\Database\PgBouncerConnection;
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
use Illuminate\Database\Connection;
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
        Connection::resolverFor('pgsql', function ($pdo, $database, $prefix, $config) {
            return new PgBouncerConnection($pdo, $database, $prefix, $config);
        });
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

        // Analytics/Financial-Reports read endpoints rebuild most of their
        // payload from scratch every call (only the raw orders fetch is
        // cached, ~120s — see VendorAnalyticsService/FinancialReportService),
        // so an unthrottled client — a buggy retry loop, or someone hitting
        // the API directly — can sustain heavy DB/CPU load with no barrier.
        // Keyed by the authenticated vendor rather than IP, since a shared
        // restaurant network shouldn't collapse into one bucket. 30/min
        // comfortably covers real dashboard usage (a page load fires a
        // handful of these concurrently) while still capping abuse.
        RateLimiter::for('analytics', function (Request $request) {
            return Limit::perMinute(30)->by(self::rateLimitActorKey($request));
        });

        // Financial expense create/update/delete/upload-attachment — these
        // were previously unthrottled entirely (2026-09-07 audit finding),
        // unlike every other Analytics/Financial-Reports route. Writes, not
        // reads, so the risk is DB/storage bloat (unlimited expense rows,
        // unlimited receipt uploads) rather than CPU load — a lower, write-
        // appropriate limit than the 30/min read bucket above, still well
        // above any realistic burst of a vendor entering several expenses in
        // one sitting.
        RateLimiter::for('financial-expenses', function (Request $request) {
            return Limit::perMinute(20)->by(self::rateLimitActorKey($request));
        });

        // Customer menu-item detail view — unauthenticated by design (guests
        // browse before logging in), but every hit also writes a
        // MenuItemView row that feeds Analytics' "high interest, low
        // conversion" insight (see App\Models\MenuItemView's doc comment).
        // Unlike its sibling `table/pin` route, this had no throttle at all,
        // so a scripted loop could inflate an item's view count and
        // manufacture a misleading insight card for any vendor with no
        // guest behavior behind it (2026-09-08 audit finding). Keyed by IP
        // (rateLimitActorKey() falls back to it for an unauthenticated
        // request) since real guest traffic to this route never carries an
        // auth token. 30/min comfortably covers a guest browsing a menu
        // while still capping scripted abuse.
        RateLimiter::for('menu-item-view', function (Request $request) {
            return Limit::perMinute(30)->by(self::rateLimitActorKey($request));
        });
    }

    /**
     * A rate-limit bucket key scoped to the authenticated actor's TABLE, not
     * just their bare id — `$request->user()?->id` alone let an unrelated
     * Vendor and TeamMember with the same numeric id share one bucket
     * (2026-09-08 audit finding). Not exploitable today (staff tokens are
     * already blocked from every analytics/financial-expenses route at the
     * `EnsureStaffCanAccessVendorRoute` middleware layer), but the guard is
     * cheap and keeps this correct if that allowlist is ever loosened. Same
     * `getTable()` discriminator AnalyticsController/FinancialExpenseController's
     * own `authorizeVendor()` already uses to distinguish actor types.
     */
    private static function rateLimitActorKey(Request $request): string
    {
        $user = $request->user();

        return $user ? $user->getTable().':'.$user->getKey() : (string) $request->ip();
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
