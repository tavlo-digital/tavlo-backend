<?php

namespace App\Http\Middleware;

use App\Services\CustomerApiCache;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CacheCustomerApiResponse
{
    /** @var array<int, string> */
    private const CACHEABLE_ROUTES = [
        'customer.categories',
        'customer.payment-methods',
        'customer.restaurants.*',
        'customer.table.status',
        'customer.me',
        'customer.profile.show',
        'customer.table.session.status',
        'customer.table.order.start',
        'customer.table.history',
        'customer.cart.index',
        'customer.orders.history',
        'customer.orders.restaurants',
        'customer.orders.vendor',
        'customer.orders.tracking',
        'customer.orders.show',
        'customer.receipts.*',
        'customer.reservations.*',
        'customer.loyalty.*',
        'customer.favorites.index',
        'customer.reviews.index',
        'customer.reviews.item',
        'customer.reviews.sessions',
        'customer.reviews.session-orders',
        'customer.privacy.requests',
    ];

    public function __construct(private readonly CustomerApiCache $cache) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->shouldCache($request)) {
            return $next($request);
        }

        $key = $this->cache->key($request);

        try {
            $cached = $this->cache->repository()->get($key);
            if (is_array($cached) && array_key_exists('body', $cached)) {
                return response()->json($cached['body'], (int) ($cached['status'] ?? 200))
                    ->header('X-Tavlo-Cache', 'HIT');
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        $response = $next($request);

        if ($response instanceof JsonResponse && $response->isSuccessful()) {
            try {
                $this->cache->repository()->put($key, [
                    'body' => $response->getData(true),
                    'status' => $response->getStatusCode(),
                ], max(1, (int) config('services.customer_api_cache.ttl', 120)));
                $response->headers->set('X-Tavlo-Cache', 'MISS');
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $response;
    }

    private function shouldCache(Request $request): bool
    {
        if (! $this->cache->enabled() || ! $request->isMethod('GET')) {
            return false;
        }

        $name = (string) $request->route()?->getName();

        foreach (self::CACHEABLE_ROUTES as $pattern) {
            if (fnmatch($pattern, $name)) {
                return true;
            }
        }

        return false;
    }
}
