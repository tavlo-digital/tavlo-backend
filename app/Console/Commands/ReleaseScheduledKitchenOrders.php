<?php

namespace App\Console\Commands;

use App\Services\KitchenOrderReleaseService;
use Illuminate\Console\Command;

class ReleaseScheduledKitchenOrders extends Command
{
    protected $signature = 'kitchen-orders:release-scheduled';

    protected $description = 'Release paid pickup and takeaway orders to kitchen inside the preparation window.';

    public function handle(KitchenOrderReleaseService $releases): int
    {
        $released = $releases->releaseDueOrders();

        $this->info("Released {$released} order(s) to kitchen.");

        return self::SUCCESS;
    }
}
