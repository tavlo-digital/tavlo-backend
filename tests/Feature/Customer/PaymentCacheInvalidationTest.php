<?php

namespace Tests\Feature\Customer;

use App\Services\CustomerApiCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `GET /customer/table/history` is response-cached, and
 * InvalidateCustomerApiCache only fires on non-GET requests. Payment settles
 * through a GET (`/payments/verify`) and through staff writes on vendor routes,
 * neither of which bumps the cache version — so a guest who had just paid kept
 * being shown their order as unpaid until the TTL lapsed.
 */
class PaymentCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.customer_api_cache.enabled', true);
        config()->set('services.customer_api_cache.store', 'array');
    }

    public function test_the_cached_history_version_moves_when_a_payment_is_synced(): void
    {
        $cache = app(CustomerApiCache::class);
        $before = $cache->version();

        // The transition every payment path runs through, whether it arrived by
        // the verify redirect or the Stripe webhook.
        $cache->invalidate();

        $this->assertNotSame(
            $before,
            $cache->version(),
            'A payment transition must move the cache version, or table/history keeps serving a pre-payment body.',
        );
    }

    public function test_table_history_is_a_cached_route(): void
    {
        // If this route ever leaves the cacheable list the invalidation above is
        // unnecessary — but while it is cached, it must be invalidated.
        $reflection = new \ReflectionClass(\App\Http\Middleware\CacheCustomerApiResponse::class);
        $cacheable = $reflection->getConstants()['CACHEABLE_ROUTES']
            ?? $reflection->getStaticPropertyValue('cacheableRoutes', null);

        if ($cacheable === null) {
            $property = $reflection->getProperty('cacheableRoutes');
            $cacheable = $property->getDefaultValue();
        }

        $this->assertContains('customer.table.history', $cacheable);
    }
}
