<?php

namespace App\Services;

use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Enqueue side-effect work only after an HTTP response has been flushed.
 *
 * Laravel's PendingDispatch::afterResponse() executes the job synchronously
 * during application termination. Deferring the queue push instead keeps the
 * expensive job on its configured worker queue while removing the Redis push
 * from the measured API request path.
 */
class DeferredQueueDispatcher
{
    public static function dispatch(ShouldQueue $job): void
    {
        $enqueue = static fn () => dispatch($job)->afterCommit();

        // Queue workers and Artisan commands do not have an HTTP response to
        // flush, so enqueue immediately in those processes.
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            $enqueue();

            return;
        }

        defer($enqueue)->always();
    }
}
