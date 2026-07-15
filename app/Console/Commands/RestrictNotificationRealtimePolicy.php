<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestrictNotificationRealtimePolicy extends Command
{
    protected $signature = 'notifications:restrict-realtime {--force : Remove the public policy without confirmation}';

    protected $description = 'Remove the legacy public notifications SELECT policy after scoped realtime subscriptions are verified.';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Realtime notification policies can only be changed on PostgreSQL.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm(
            'Have customer, vendor, waiter, and kitchen realtime subscriptions been verified with the scoped policy?',
        )) {
            $this->warn('The public policy was left unchanged.');

            return self::SUCCESS;
        }

        DB::statement('DROP POLICY IF EXISTS "Allow realtime select" ON public.notifications');

        $this->info('The legacy public notifications SELECT policy has been removed.');

        return self::SUCCESS;
    }
}
