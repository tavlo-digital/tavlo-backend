<?php

namespace App\Http\Middleware;

use App\Jobs\RecordCustomerSessionActivity;
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
        );

        if ((bool) config('services.session_activity.queue_enabled', false)) {
            dispatch($job)->afterResponse();
        } else {
            $job->handle();
        }

        return $response;
    }
}
