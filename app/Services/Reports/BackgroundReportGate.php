<?php

namespace App\Services\Reports;

use App\Models\FinancialReportJob;
use App\Models\Vendor;
use Illuminate\Support\Facades\Cache;

/**
 * Decides whether a Financial Reports/Analytics request should run live
 * (today's behavior, unchanged) or be handed to the background-job flow —
 * see FinancialReportJob's doc comment for the full design and why.
 *
 * A vendor may have at most one report "processing" at a time, across BOTH
 * features (product decision, 2026-09-07) — not a technical limit, a
 * deliberate choice to cap how much heavy work one vendor can trigger at
 * once. A small/cheap request is never blocked by this, regardless of
 * whether a big one is already running: the block only applies when the
 * NEW request is itself big enough to need the background path.
 */
class BackgroundReportGate
{
    /**
     * A `processing` row older than this is treated as abandoned, not
     * still genuinely running. Both GenerateAnalyticsReportJob and
     * GenerateFinancialReportJob already mark their own row `failed` on
     * any exception (via handle()'s catch) or on final retry exhaustion
     * (via failed()) — but a queue worker that crashes or gets restarted
     * mid-job (a deploy, an OOM, an infra hiccup) dies before either of
     * those ever runs, leaving the row stuck at `processing` forever. Since
     * this is the ONE check standing between a vendor and ever running
     * another large report on either page, an unbounded "processing"
     * lookup with no age limit turns a single bad worker restart into a
     * permanent lockout requiring a manual database fix (2026-09-08 audit
     * finding). Set well above both jobs' own 600s `$timeout` to also cover
     * realistic queue-wait time before a job even starts running.
     */
    private const STALE_PROCESSING_MINUTES = 20;

    public function decide(
        Vendor $vendor,
        string $source,
        int $orderCount,
        string $periodKey,
        ?string $periodFrom,
        ?string $periodTo,
    ): BackgroundReportDecision {
        $threshold = (int) config('reports.async_order_threshold');

        if ($orderCount <= $threshold) {
            return BackgroundReportDecision::runLive();
        }

        // Everything below only ever runs for the rare "this needs the
        // background path" case, so a lock here costs nothing on the
        // overwhelmingly common small-request path above. Without it, two
        // near-simultaneous big requests for the same vendor (a double-click,
        // two open tabs) could both read "nothing processing yet" before
        // either had inserted its row, and both start a heavy job — defeating
        // the one-at-a-time cap this class exists to enforce (2026-09-07
        // audit finding). `Cache::lock()` uses this app's database cache
        // store (CACHE_STORE=database), which backs atomic locks with its own
        // `cache_locks` table — this works whether or not the two requests
        // land on the same PHP-FPM worker. A losing request waits up to 5s
        // for the winner to finish deciding (a handful of cheap queries plus
        // one insert, not the actual report build) rather than erroring.
        return Cache::lock("report-gate:vendor:{$vendor->id}", 10)->block(5, function () use (
            $vendor, $source, $periodKey, $periodFrom, $periodTo,
        ): BackgroundReportDecision {
            $blocking = FinancialReportJob::query()
                ->where('vendor_id', $vendor->id)
                ->where('status', FinancialReportJob::STATUS_PROCESSING)
                ->first();

            if ($blocking) {
                if ($blocking->requested_at->lt(now()->subMinutes(self::STALE_PROCESSING_MINUTES))) {
                    // Abandoned — whatever worker owned this job is gone and
                    // will never mark it failed on its own. Clear it here
                    // (still inside this same lock, so no new race with a
                    // concurrent decide() call) and fall through to the
                    // normal decision logic below as if nothing were
                    // processing.
                    $blocking->update([
                        'status' => FinancialReportJob::STATUS_FAILED,
                        'error_message' => sprintf(
                            'Abandoned — no result after %d minutes, the worker likely died or restarted mid-job.',
                            self::STALE_PROCESSING_MINUTES,
                        ),
                        'completed_at' => now(),
                    ]);
                } else {
                    return BackgroundReportDecision::blocked($blocking);
                }
            }

            $ttlMinutes = (int) config('reports.ready_result_ttl_minutes');

            $ready = FinancialReportJob::query()
                ->where('vendor_id', $vendor->id)
                ->where('source', $source)
                ->where('period_key', $periodKey)
                ->where('period_from', $periodFrom)
                ->where('period_to', $periodTo)
                ->where('status', FinancialReportJob::STATUS_READY)
                ->where('completed_at', '>=', now()->subMinutes($ttlMinutes))
                ->latest('completed_at')
                ->first();

            if ($ready) {
                return BackgroundReportDecision::reuseReady($ready);
            }

            $job = FinancialReportJob::create([
                'vendor_id' => $vendor->id,
                'source' => $source,
                'period_key' => $periodKey,
                'period_from' => $periodFrom,
                'period_to' => $periodTo,
                'status' => FinancialReportJob::STATUS_PROCESSING,
                'requested_at' => now(),
            ]);

            return BackgroundReportDecision::started($job);
        });
    }
}
