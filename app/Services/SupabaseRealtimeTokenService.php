<?php

namespace App\Services;

use RuntimeException;

class SupabaseRealtimeTokenService
{
    public function issue(array $claims): array
    {
        $supabaseUrl = rtrim((string) config('services.supabase.url'), '/');
        $ttl = max(300, (int) config('services.supabase.realtime_token_ttl', 900));

        if ($supabaseUrl === '') {
            throw new RuntimeException('Supabase URL is not configured.');
        }

        $ecKey = trim((string) config('services.supabase.realtime_signing_key'));
        if ($ecKey !== '') {
            return $this->issueEc($claims, $supabaseUrl, $ttl);
        }

        $jwtSecret = trim((string) config('services.supabase.jwt_secret'));
        if ($jwtSecret !== '') {
            return $this->issueHmac($claims, $jwtSecret, $supabaseUrl, $ttl);
        }

        throw new RuntimeException('Supabase Realtime signing is not configured.');
    }

    private function issueHmac(array $claims, string $secret, string $supabaseUrl, int $ttl): array
    {
        $issuedAt = now()->timestamp;
        $expiresAt = $issuedAt + $ttl;

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $payload = [
            'iss' => $supabaseUrl.'/auth/v1',
            'aud' => 'authenticated',
            'role' => 'authenticated',
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            ...$claims,
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $unsignedToken = $encodedHeader.'.'.$encodedPayload;

        $signature = hash_hmac('sha256', $unsignedToken, base64_decode($secret), true);

        return [
            'token' => $unsignedToken.'.'.$this->base64UrlEncode($signature),
            'expires_at' => now()->setTimestamp($expiresAt)->toISOString(),
        ];
    }

    private function issueEc(array $claims, string $supabaseUrl, int $ttl): array
    {
        $privateKey = $this->privateKey();
        $keyId = trim((string) config('services.supabase.realtime_signing_key_id'));

        if ($keyId === '') {
            throw new RuntimeException('Supabase Realtime signing is not configured.');
        }

        $issuedAt = now()->timestamp;
        $expiresAt = $issuedAt + $ttl;

        $header = ['alg' => 'ES256', 'kid' => $keyId, 'typ' => 'JWT'];
        $payload = [
            'iss' => $supabaseUrl.'/auth/v1',
            'aud' => 'authenticated',
            'role' => 'authenticated',
            'iat' => $issuedAt,
            'exp' => $expiresAt,
            ...$claims,
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $unsignedToken = $encodedHeader.'.'.$encodedPayload;

        if (! openssl_sign($unsignedToken, $derSignature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign Supabase Realtime token.');
        }

        return [
            'token' => $unsignedToken.'.'.$this->base64UrlEncode($this->derToJose($derSignature)),
            'expires_at' => now()->setTimestamp($expiresAt)->toISOString(),
        ];
    }

    private function privateKey(): \OpenSSLAsymmetricKey
    {
        $configured = trim((string) config('services.supabase.realtime_signing_key'));
        if ($configured === '') {
            throw new RuntimeException('Supabase Realtime signing is not configured.');
        }

        $pem = str_replace('\\n', "\n", $configured);
        if (! str_contains($pem, 'BEGIN')) {
            $decoded = base64_decode($configured, true);
            if ($decoded !== false) {
                $pem = $decoded;
            }
        }

        $key = openssl_pkey_get_private($pem);
        if ($key === false || openssl_pkey_get_details($key)['type'] !== OPENSSL_KEYTYPE_EC) {
            throw new RuntimeException('Supabase Realtime signing key must be an EC private key.');
        }

        return $key;
    }

    private function derToJose(string $signature): string
    {
        $offset = 0;
        if (ord($signature[$offset++]) !== 0x30) {
            throw new RuntimeException('Invalid ECDSA signature.');
        }
        $this->readDerLength($signature, $offset);

        if (ord($signature[$offset++]) !== 0x02) {
            throw new RuntimeException('Invalid ECDSA signature.');
        }
        $rLength = $this->readDerLength($signature, $offset);
        $r = substr($signature, $offset, $rLength);
        $offset += $rLength;

        if (ord($signature[$offset++]) !== 0x02) {
            throw new RuntimeException('Invalid ECDSA signature.');
        }
        $sLength = $this->readDerLength($signature, $offset);
        $s = substr($signature, $offset, $sLength);

        return $this->normalizeInteger($r).$this->normalizeInteger($s);
    }

    private function readDerLength(string $value, int &$offset): int
    {
        $length = ord($value[$offset++]);
        if (($length & 0x80) === 0) {
            return $length;
        }

        $bytes = $length & 0x7F;
        $length = 0;
        for ($i = 0; $i < $bytes; $i++) {
            $length = ($length << 8) | ord($value[$offset++]);
        }

        return $length;
    }

    private function normalizeInteger(string $value): string
    {
        $value = ltrim($value, "\0");
        if (strlen($value) > 32) {
            $value = substr($value, -32);
        }

        return str_pad($value, 32, "\0", STR_PAD_LEFT);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
