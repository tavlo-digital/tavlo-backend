<?php

namespace App\Console\Commands;

use App\Jobs\FiscalizeOrder;
use App\Models\FiscalReceipt;
use Illuminate\Console\Command;

/**
 * Requeues receipts whose signing exhausted its retries, and picks up any that
 * were recorded but never dispatched — a queue restart mid-flight, say.
 */
class RetryFailedFiscalization extends Command
{
    protected $signature = 'fiskaly:retry-failed {--max-attempts=25 : Give up on a receipt after this many tries}';

    protected $description = 'Requeue fiscal receipts that are still unsigned.';

    public function handle(): int
    {
        $maxAttempts = (int) $this->option('max-attempts');

        $receipts = FiscalReceipt::query()
            ->whereIn('state', [FiscalReceipt::STATE_PENDING, FiscalReceipt::STATE_FAILED])
            ->where('attempts', '<', $maxAttempts)
            ->where('updated_at', '<', now()->subMinutes(5))
            ->orderBy('id')
            ->limit(200)
            ->get();

        foreach ($receipts as $receipt) {
            FiscalizeOrder::dispatch($receipt->id);
        }

        if ($receipts->isNotEmpty()) {
            $this->info("Requeued {$receipts->count()} unsigned receipt(s).");
        }

        $stuck = FiscalReceipt::where('state', FiscalReceipt::STATE_FAILED)
            ->where('attempts', '>=', $maxAttempts)
            ->count();

        if ($stuck > 0) {
            $this->warn("{$stuck} receipt(s) have exceeded {$maxAttempts} attempts and need attention.");
        }

        return self::SUCCESS;
    }
}
