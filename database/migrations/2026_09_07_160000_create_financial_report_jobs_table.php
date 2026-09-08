<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the "prepare this in the background" path for Financial Reports and
 * Analytics: a vendor's period selection that would otherwise load too much
 * data for one HTTP request (see FinancialReportService/VendorAnalyticsService's
 * own doc comments on the 2026-09-07 stress test) is instead handed to a
 * queued job, tracked here, and the vendor is notified through the existing
 * notification system when it's done.
 *
 * One table for both features (`source` distinguishes them) so "does this
 * vendor already have something processing" is a single check regardless of
 * which page they triggered it from — a vendor may only have one big report
 * generating at a time, by product decision (2026-09-07), not a technical
 * limit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_report_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('source', 20); // 'financial_reports' | 'analytics'
            $table->string('period_key', 20); // FinancialReportPeriod/AnalyticsPeriod key, e.g. 'year', 'custom'
            // Only meaningful (non-null) for period_key='custom' — every other
            // key is resolved fresh from "now" the same way the synchronous
            // path already does, so storing null here and re-resolving by
            // key alone reproduces the identical window.
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('status', 20)->default('processing'); // 'processing' | 'ready' | 'failed'
            // The finished report payload — the exact same JSON shape
            // FinancialReportService::build()/VendorAnalyticsService::build()
            // already return, stored so a vendor revisiting this period
            // shortly after being notified gets it instantly instead of
            // triggering a second, identical, expensive run.
            $table->longText('result')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // "Does this vendor already have something processing" — the
            // hot-path check on every big-report request.
            $table->index(['vendor_id', 'status']);
            // "Is there already a ready result for this exact period" — the
            // revisit-reuse check.
            $table->index(['vendor_id', 'source', 'period_key', 'period_from', 'period_to', 'status'], 'financial_report_jobs_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_report_jobs');
    }
};
