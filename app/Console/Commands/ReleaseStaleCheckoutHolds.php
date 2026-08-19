<?php

namespace App\Console\Commands;

use App\Services\CheckoutHoldService;
use Illuminate\Console\Command;

/**
 * Frees orders held by a checkout nobody came back from.
 *
 * A hold is taken when a customer opens the payment step and dropped when they
 * go back, pay, or cancel — but a closed tab does none of those, and the rest
 * of the table would stay locked out of items they still have to settle.
 */
class ReleaseStaleCheckoutHolds extends Command
{
    protected $signature = 'payments:release-stale-checkout-holds';

    protected $description = 'Release checkout holds left behind by an abandoned payment step.';

    public function handle(CheckoutHoldService $holds): int
    {
        $released = $holds->releaseExpired();

        if ($released > 0) {
            $this->info("Released {$released} stale checkout hold(s).");
        }

        return self::SUCCESS;
    }
}
