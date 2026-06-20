<?php

namespace App\Console\Commands;

use App\Models\Notification;
use Illuminate\Console\Command;

class PruneSilentNotifications extends Command
{
    protected $signature = 'notifications:prune-silent';

    protected $description = 'Delete silent realtime invalidation notifications older than 24 hours.';

    public function handle(): int
    {
        $deleted = Notification::query()
            ->where('is_silent', true)
            ->where('created_at', '<', now()->subDay())
            ->delete();

        $this->info("Deleted {$deleted} silent notifications.");

        return self::SUCCESS;
    }
}
