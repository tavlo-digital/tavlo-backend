<?php

namespace App\Services;

use Illuminate\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CustomerApiCache
{
    private const VERSION_KEY = 'customer-api-cache:version';

    public function enabled(): bool
    {
        return (bool) config('services.customer_api_cache.enabled', false);
    }

    public function repository(): Repository
    {
        return Cache::store((string) config('services.customer_api_cache.store', 'redis'));
    }

    public function version(): int
    {
        try {
            return (int) ($this->repository()->get(self::VERSION_KEY, 1));
        } catch (Throwable $exception) {
            report($exception);

            return 1;
        }
    }

    public function invalidate(): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            if (! $this->repository()->has(self::VERSION_KEY)) {
                $this->repository()->forever(self::VERSION_KEY, 1);
            }
            $this->repository()->increment(self::VERSION_KEY);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function key(Request $request): string
    {
        $customerId = $request->user('customer')?->id ?? 0;
        $locale = strtolower((string) $request->header('Accept-Language', 'en'));
        $orderMode = match (strtolower(trim((string) $request->header('X-Order-Mode')))) {
            'pickup' => 'pickup',
            'takeaway' => 'takeaway',
            default => 'dine_in',
        };
        $identity = implode('|', [
            $this->version(),
            $request->route()?->getName() ?? $request->path(),
            $request->path(),
            http_build_query($request->query()),
            $customerId,
            $locale,
            $orderMode,
        ]);

        return 'customer-api-cache:response:'.hash('sha256', $identity);
    }
}
