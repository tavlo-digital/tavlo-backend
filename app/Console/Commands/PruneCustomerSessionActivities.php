<?php

namespace App\Console\Commands;

use App\Models\CustomerSessionActivity;
use Illuminate\Console\Command;

class PruneCustomerSessionActivities extends Command
{
    protected $signature = 'session-activities:prune';

    protected $description = 'Delete customer session activity rows older than the configured retention period.';

    public function handle(): int
    {
        $retentionDays = max(1, (int) config('services.session_activity.retention_days', 30));
        $deleted = CustomerSessionActivity::query()
            ->where('created_at', '<', now()->subDays($retentionDays))
            ->delete();

        $this->info("Deleted {$deleted} customer session activities older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
