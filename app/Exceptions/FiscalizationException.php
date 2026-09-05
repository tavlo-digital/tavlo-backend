<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Raised when a receipt or a cash register cannot be fiscalized. Never
 * swallowed: an unsigned receipt has to stay visibly unsigned rather than
 * quietly look final.
 *
 * The context is folded into the message because that is what gets logged and
 * stored on the device — "fiskaly rejected the request" on its own tells an
 * admin nothing they can act on.
 */
class FiscalizationException extends RuntimeException
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public readonly string $summary,
        public readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct(self::describe($summary, $context), 0, $previous);
    }

    /**
     * The provider's own words, where it gave any: what an admin needs to take
     * back to the restaurant.
     */
    public function providerDetail(): ?string
    {
        $body = $this->context['body'] ?? null;

        if (is_string($body)) {
            return trim($body) ?: null;
        }

        if (! is_array($body)) {
            return null;
        }

        $parts = array_filter([
            $body['code'] ?? $body['error'] ?? null,
            $body['message'] ?? $body['detail'] ?? $body['error_description'] ?? null,
        ], fn ($part) => is_scalar($part) && trim((string) $part) !== '');

        return $parts ? implode(' — ', array_map('strval', $parts)) : null;
    }

    /**
     * Whether the provider rejected what the restaurant supplied, as opposed to
     * something on our side.
     *
     * The difference decides whether a legal change is sent back to the vendor
     * to correct, or simply held for the admin to approve again: a fiskaly
     * outage or a wrong API key of ours is not the restaurant's fault and must
     * not cost them their submission.
     */
    public function isVendorDataRejection(): bool
    {
        $status = $this->context['status'] ?? null;

        if (($this->context['responsibility'] ?? null) === 'platform') {
            return false;
        }

        // Our own token exchange, a transport failure, or a provider outage.
        if (($this->context['path'] ?? null) === '/auth' || ! is_int($status) || $status >= 500) {
            return false;
        }

        return $status >= 400;
    }

    /** @param array<string, mixed> $context */
    private static function describe(string $summary, array $context): string
    {
        if ($context === []) {
            return $summary;
        }

        $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? $summary : $summary.' '.$encoded;
    }
}
