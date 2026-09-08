<?php

/**
 * Config for the Financial Reports / Analytics "prepare in the background"
 * path — see App\Models\FinancialReportJob's doc comment. Only ranges whose
 * order count exceeds `async_order_threshold` go through the background
 * flow; everything else still loads live exactly as before, so most vendors
 * never see this path exist.
 */
return [
    // Re-measured 2026-09-08 against a realistic seeded vendor (default PHP
    // 128MB memory_limit) — the previous default of 2000 was set from an
    // earlier, less thorough pass and turned out to already be uncomfortably
    // close to the edge: ~2,000 orders peaked around 100MB (fine, but with
    // little real margin) and ~2,900 orders reliably crashed with an
    // out-of-memory fatal error while simply building the order/cart-item
    // collections, independent of caching. Lowered to keep the live
    // (synchronous, web-request) path comfortably clear of that line.
    'async_order_threshold' => (int) env('REPORTS_ASYNC_ORDER_THRESHOLD', 1500),

    // Hard ceiling enforced by FinancialReportService/VendorAnalyticsService
    // themselves (not just the controller's live-vs-background split above)
    // — ANY request for a period with more orders than this, live or
    // background, is refused with a clear
    // Reports\ReportPeriodTooLargeException instead of being attempted and
    // risking an uncatchable out-of-memory crash (2026-09-08 audit finding:
    // reproduced directly — a background job hitting this crash dies
    // mid-run with no chance to mark itself failed, since a PHP fatal
    // memory-exhaustion error cannot be caught, leaving the vendor blocked
    // for 20 minutes only to hit the identical crash again on retry). Stays
    // safely below the ~2,900-order point that reliably crashed in testing;
    // revisit this ceiling only alongside a real change to how much of the
    // order/cart-item history gets hydrated into memory per report (a
    // streaming/pre-aggregated rewrite), not by raising the number alone.
    'max_order_count' => (int) env('REPORTS_MAX_ORDER_COUNT', 2200),

    // How long a finished background result stays reusable on revisit
    // before a fresh request re-triggers generation instead of serving a
    // result that may no longer reflect a since-added expense or refund.
    'ready_result_ttl_minutes' => (int) env('REPORTS_READY_RESULT_TTL_MINUTES', 60),
];
