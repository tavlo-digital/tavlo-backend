<?php

namespace App\Http\Middleware;

use App\Models\CustomerSessionActivity;
use App\Models\TableScanSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackSessionActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $customer = $request->user('customer');

        if (! $customer) {
            return $response;
        }

        $session = TableScanSession::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if ($session) {
            CustomerSessionActivity::create([
                'table_scan_session_id' => $session->id,
                'customer_id'           => $customer->id,
                'endpoint'              => $request->path(),
                'method'                => $request->method(),
            ]);
        }

        return $response;
    }
}
