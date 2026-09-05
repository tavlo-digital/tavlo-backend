<?php

namespace App\Services\Fiscal;

use App\Exceptions\FiscalizationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Thin REST client for a single fiskaly product host.
 *
 * SIGN AT and SIGN DE are separate APIs on separate hosts, each with its own
 * /auth, so one instance is bound per country rather than one shared client.
 * fiskaly's own PHP SDK is archived, which is why this talks to the REST API
 * directly.
 */
class FiskalyClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $cacheKey,
        private readonly ?string $apiKey = null,
        private readonly ?string $apiSecret = null,
    ) {}

    /** @param array<string, mixed> $body */
    public function put(string $path, array $body): array
    {
        return $this->send('put', $path, $body);
    }

    /** @param array<string, mixed> $body */
    public function patch(string $path, array $body): array
    {
        return $this->send('patch', $path, $body);
    }

    /** @param array<string, mixed> $body */
    public function post(string $path, array $body = []): array
    {
        return $this->send('post', $path, $body);
    }

    public function get(string $path): array
    {
        return $this->send('get', $path, []);
    }

    /**
     * Access tokens last about a day. Cached just short of that so a burst of
     * signings shares one, and dropped on a 401 so a rotated key recovers on
     * the next attempt rather than failing until the TTL expires.
     */
    private function accessToken(bool $forget = false): string
    {
        if ($forget) {
            Cache::forget($this->cacheKey);
        }

        $token = Cache::remember($this->cacheKey, now()->addHours(12), function () {
            $credentials = [
                'api_key' => $this->apiKey ?? (string) config('services.fiskaly.api_key'),
                'api_secret' => $this->apiSecret ?? (string) config('services.fiskaly.api_secret'),
            ];

            if ($credentials['api_key'] === '' || $credentials['api_secret'] === '') {
                throw new FiscalizationException('fiskaly credentials are not configured.');
            }

            $response = $this->http()->post($this->url('/auth'), $credentials);

            if ($response->failed()) {
                throw new FiscalizationException('fiskaly authentication failed.', [
                    'path' => '/auth',
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ]);
            }

            return (string) $response->json('access_token');
        });

        if ($token === '') {
            Cache::forget($this->cacheKey);

            throw new FiscalizationException('fiskaly returned an empty access token.');
        }

        return $token;
    }

    /** @param array<string, mixed> $body */
    private function send(string $method, string $path, array $body, bool $retriedAuth = false): array
    {
        try {
            $response = $this->http()
                ->withToken($this->accessToken($retriedAuth))
                ->{$method}($this->url($path), $body);
        } catch (FiscalizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new FiscalizationException(
                'fiskaly request failed: '.$exception->getMessage(),
                ['method' => $method, 'path' => $path],
                $exception,
            );
        }

        // A cached token that the server no longer accepts is worth exactly one
        // retry with a fresh one.
        if ($response->status() === 401 && ! $retriedAuth) {
            return $this->send($method, $path, $body, true);
        }

        return $this->decode($response, $method, $path);
    }

    private function decode(Response $response, string $method, string $path): array
    {
        if ($response->failed()) {
            throw new FiscalizationException(
                'fiskaly rejected the request with HTTP '.$response->status().'.',
                [
                    'method' => strtoupper($method),
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                ],
            );
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }

    private function http(): PendingRequest
    {
        return Http::asJson()
            ->acceptJson()
            ->timeout((int) config('services.fiskaly.timeout', 15))
            ->retry(
                max(1, (int) config('services.fiskaly.retries', 3)),
                max(0, (int) config('services.fiskaly.retry_delay_ms', 500)),
                // A rejected payload will be rejected identically next time.
                // Only retry transport faults and the server's own 5xx.
                fn (Throwable $e) => ! $e instanceof RequestException || $e->response->serverError(),
                throw: false,
            );
    }

    private function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }
}
