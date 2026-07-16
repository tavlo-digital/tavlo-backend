<?php

namespace App\Http\Middleware;

use App\Services\CustomerApiCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvalidateCustomerApiCache
{
    public function __construct(private readonly CustomerApiCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') && $response->isSuccessful()) {
            $this->cache->invalidate();
        }

        return $response;
    }
}
