<?php

namespace App\Services\Fiscal;

use App\Exceptions\FiscalizationException;
use App\Models\FiscalDevice;
use App\Models\Vendor;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Creates the managed fiskaly Unit and scoped API credentials for a vendor.
 *
 * The key in .env belongs to Tavlo's Group and must never be used to create a
 * restaurant's SCU/TSS. Each restaurant gets a Unit and a dedicated key; its
 * secret is returned only once and is immediately stored in FiscalDevice's
 * encrypted credentials cast.
 */
class FiskalyManagementClient
{
    /**
     * @param  array<string, mixed>  $legal
     */
    public function ensureVendorOrganization(
        Vendor $vendor,
        FiscalDevice $device,
        array $legal = [],
    ): FiscalDevice {
        $credentials = $device->credentials ?? [];
        $hasScopedCredentials = $device->fiskaly_organization_id
            && filled($credentials['fiskaly_api_key'] ?? null)
            && filled($credentials['fiskaly_api_secret'] ?? null);

        if ($hasScopedCredentials && $legal === []) {
            return $device;
        }

        $auth = $this->authenticate();
        $groupId = (string) ($auth['access_token_claims']['organization_id'] ?? '');

        if (! Str::isUuid($groupId)) {
            throw new FiscalizationException(
                'fiskaly Management authentication did not return a Group organization id.',
                ['path' => '/auth', 'responsibility' => 'platform'],
            );
        }

        $organizationPayload = $this->organizationPayload($vendor, $groupId, $legal);
        $organizationId = (string) ($device->fiskaly_organization_id ?? '');

        if ($organizationId === '') {
            // Recover an organization created by an earlier request whose
            // response was lost before Tavlo could persist its id.
            $organizationId = $this->findVendorOrganization($vendor, $groupId);

            if ($organizationId === '') {
                $organization = $this->request(
                    'post',
                    '/organizations',
                    $organizationPayload,
                    responsibility: 'vendor',
                );
                $organizationId = (string) ($organization['_id'] ?? '');
            } else {
                $this->request(
                    'patch',
                    "/organizations/{$organizationId}",
                    $organizationPayload,
                    responsibility: 'vendor',
                );
            }

            if (! Str::isUuid($organizationId)) {
                throw new FiscalizationException(
                    'fiskaly did not return the managed Unit id.',
                    ['path' => '/organizations', 'responsibility' => 'platform'],
                );
            }

            $device->forceFill(['fiskaly_organization_id' => $organizationId])->save();
        } elseif ($legal !== []) {
            // Keep HUB's legal address in sync when a later approved request
            // corrects the restaurant details. This does not create a new
            // Unit, SCU/TSS, or register/client.
            $this->request(
                'patch',
                "/organizations/{$organizationId}",
                $organizationPayload,
                responsibility: 'vendor',
            );
        }

        $credentials = $device->credentials ?? [];

        if (filled($credentials['fiskaly_api_key'] ?? null)
            && filled($credentials['fiskaly_api_secret'] ?? null)) {
            return $device->refresh();
        }

        $keyName = $this->keyName($vendor);
        $apiKey = $this->findRecoverableApiKey($organizationId, $keyName);

        if ($apiKey === null) {
            $apiKey = $this->request(
                'post',
                "/organizations/{$organizationId}/api-keys",
                [
                    'name' => $keyName,
                    'status' => 'enabled',
                    'managed_by_organization_id' => $groupId,
                    'metadata' => ['tavlo_vendor_id' => (string) $vendor->getKey()],
                ],
                responsibility: 'platform',
            );
        }

        $key = (string) ($apiKey['key'] ?? '');
        $secret = (string) ($apiKey['secret'] ?? '');

        if ($key === '' || $secret === '') {
            throw new FiscalizationException(
                'The fiskaly Unit API key exists but its one-time secret is unavailable. Delete that Unit key in HUB and retry approval.',
                [
                    'path' => "/organizations/{$organizationId}/api-keys",
                    'responsibility' => 'platform',
                ],
            );
        }

        $device->forceFill([
            'fiskaly_api_key_id' => $apiKey['_id'] ?? null,
            'credentials' => [
                ...($device->credentials ?? []),
                'fiskaly_api_key' => $key,
                'fiskaly_api_secret' => $secret,
            ],
        ])->save();

        return $device->refresh();
    }

    /** @return array<string, mixed> */
    private function authenticate(bool $forget = false): array
    {
        $apiKey = (string) (config('services.fiskaly.api_key')
            ?: config('services.fiskaly.management.api_key'));
        $apiSecret = (string) (config('services.fiskaly.api_secret')
            ?: config('services.fiskaly.management.api_secret'));

        if ($apiKey === '' || $apiSecret === '') {
            throw new FiscalizationException(
                'fiskaly Management credentials are not configured.',
                ['path' => '/auth', 'responsibility' => 'platform'],
            );
        }

        $cacheKey = 'fiskaly:management:token:'.hash('sha256', $apiKey);

        if ($forget) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, now()->addMinutes(4), function () use ($apiKey, $apiSecret) {
            $response = $this->http()->post($this->url('/auth'), [
                'api_key' => $apiKey,
                'api_secret' => $apiSecret,
            ]);

            return $this->decode($response, 'POST', '/auth', 'platform');
        });
    }

    private function findVendorOrganization(Vendor $vendor, string $groupId): string
    {
        $query = http_build_query([
            'type' => 'MANAGED_ORGANIZATION',
            'managed_by_organization_id' => $groupId,
            'limit' => 5000,
        ]);
        $response = $this->request('get', '/organizations?'.$query, responsibility: 'platform');

        foreach ((array) ($response['data'] ?? []) as $organization) {
            if (is_array($organization)
                && (string) ($organization['metadata']['tavlo_vendor_id'] ?? '') === (string) $vendor->getKey()) {
                return (string) ($organization['_id'] ?? '');
            }
        }

        return '';
    }

    /** @return array<string, mixed>|null */
    private function findRecoverableApiKey(string $organizationId, string $keyName): ?array
    {
        $response = $this->request(
            'get',
            "/organizations/{$organizationId}/api-keys?status=enabled&limit=100",
            responsibility: 'platform',
        );

        foreach ((array) ($response['data'] ?? []) as $apiKey) {
            if (is_array($apiKey) && ($apiKey['name'] ?? null) === $keyName) {
                return $apiKey;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $legal
     * @return array<string, mixed>
     */
    private function organizationPayload(Vendor $vendor, string $groupId, array $legal): array
    {
        $country = strtoupper((string) ($legal['country'] ?? $vendor->country));
        $country = match ($country) {
            'AT', 'AUSTRIA', 'ÖSTERREICH' => 'AUT',
            'DE', 'GERMANY', 'DEUTSCHLAND' => 'DEU',
            default => $country,
        };

        $name = trim((string) ($legal['legal_entity_name'] ?? $vendor->legal_entity_name));
        $displayName = trim((string) ($legal['restaurant_name'] ?? $vendor->restaurant_name ?? $vendor->name));
        $address = trim((string) ($legal['address'] ?? $vendor->address));
        $postalCode = trim((string) ($legal['postal_code'] ?? $vendor->postal_code));
        $town = trim((string) ($legal['city'] ?? $vendor->city));

        if ($name === '' || $address === '' || $postalCode === '' || $town === '') {
            throw new FiscalizationException(
                'The restaurant legal name, address, postal code, and city are required to create its fiskaly Unit.',
                ['status' => 422, 'responsibility' => 'vendor'],
            );
        }

        return array_filter([
            'name' => Str::limit($name, 255, ''),
            'display_name' => $displayName !== '' ? Str::limit($displayName, 255, '') : null,
            'vat_id' => trim((string) ($legal['vat_number'] ?? $vendor->vat_number)) ?: null,
            'address_line1' => $address,
            'zip' => $postalCode,
            'town' => $town,
            'country_code' => $country,
            'managed_by_organization_id' => $groupId,
            'metadata' => [
                'tavlo_vendor_id' => (string) $vendor->getKey(),
                'tavlo_vendor_public_id' => (string) $vendor->vendor_public_id,
            ],
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function keyName(Vendor $vendor): string
    {
        return Str::limit('tavlo-v'.(string) $vendor->getKey(), 30, '');
    }

    /**
     * Management mutations are deliberately not automatically retried: these
     * POSTs are not idempotent. A later admin retry first discovers any Unit
     * already created from the vendor metadata.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function request(
        string $method,
        string $path,
        array $body = [],
        string $responsibility = 'platform',
        bool $retriedAuth = false,
    ): array {
        try {
            $response = $this->http()
                ->withToken((string) ($this->authenticate($retriedAuth)['access_token'] ?? ''))
                ->{$method}($this->url($path), $body);
        } catch (FiscalizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new FiscalizationException(
                'fiskaly Management request failed: '.$exception->getMessage(),
                ['method' => strtoupper($method), 'path' => $path, 'responsibility' => 'platform'],
                $exception,
            );
        }

        if ($response->status() === 401 && ! $retriedAuth) {
            return $this->request($method, $path, $body, $responsibility, true);
        }

        return $this->decode($response, strtoupper($method), $path, $responsibility);
    }

    /** @return array<string, mixed> */
    private function decode(
        Response $response,
        string $method,
        string $path,
        string $responsibility,
    ): array {
        if ($response->failed()) {
            throw new FiscalizationException(
                'fiskaly Management rejected the request with HTTP '.$response->status().'.',
                [
                    'method' => $method,
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => $response->json() ?? $response->body(),
                    'responsibility' => $responsibility,
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
            ->timeout((int) config('services.fiskaly.timeout', 15));
    }

    private function url(string $path): string
    {
        $baseUrl = (string) config(
            'services.fiskaly.management.base_url',
            'https://dashboard.fiskaly.com/api/v0',
        );

        return rtrim($baseUrl, '/').'/'.ltrim($path, '/');
    }
}
