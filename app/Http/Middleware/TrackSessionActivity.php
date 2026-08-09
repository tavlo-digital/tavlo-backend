<?php

namespace App\Http\Middleware;

use App\Jobs\RecordCustomerSessionActivity;
use App\Services\DeferredQueueDispatcher;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackSessionActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $occurredAt = now()->toISOString();
        $response = $next($request);

        $customer = $request->user('customer');

        if (! $customer) {
            return $response;
        }

        $job = new RecordCustomerSessionActivity(
            (int) $customer->id,
            $request->path(),
            $request->method(),
            $occurredAt,
            $request->header('X-Order-Mode'),
        );

        if ((bool) config('services.session_activity.queue_enabled', false)) {
            DeferredQueueDispatcher::dispatch($job);
        } else {
            $job->handle();
        }

        return $response;
    }
}
