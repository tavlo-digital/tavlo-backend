<?php

namespace App\Services\Reports;

use App\Models\FinancialReportJob;

/**
 * The outcome of BackgroundReportGate::decide() — see that class's doc
 * comment. Exactly one of `job`/`blockingJob` is ever set, matching which
 * named constructor produced it.
 */
final class BackgroundReportDecision
{
    public const RUN_LIVE = 'run_live';

    public const REUSE_READY = 'reuse_ready';

    public const BLOCKED = 'blocked';

    public const STARTED = 'started';

    private function __construct(
        public readonly string $outcome,
        public readonly ?FinancialReportJob $job = null,
        public readonly ?FinancialReportJob $blockingJob = null,
    ) {}

    public static function runLive(): self
    {
        return new self(self::RUN_LIVE);
    }

    public static function reuseReady(FinancialReportJob $job): self
    {
        return new self(self::REUSE_READY, job: $job);
    }

    public static function blocked(FinancialReportJob $blockingJob): self
    {
        return new self(self::BLOCKED, blockingJob: $blockingJob);
    }

    public static function started(FinancialReportJob $job): self
    {
        return new self(self::STARTED, job: $job);
    }
}
