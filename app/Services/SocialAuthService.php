<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SocialAuthService
{
    /**
     * Verify an access token with the given provider and return user data.
     *
     * @return array{provider_id: string, email: string, first_name: string, last_name: string}
     *
     * @throws \Exception
     */
    public function verify(string $provider, string $accessToken): array
    {
        return match ($provider) {
            'google'   => $this->verifyGoogle($accessToken),
            'apple'    => $this->verifyApple($accessToken),
            'facebook' => $this->verifyFacebook($accessToken),
            default    => throw new \InvalidArgumentException("Unsupported provider: {$provider}"),
        };
    }

    protected function verifyGoogle(string $accessToken): array
    {
        $response = Http::get('https://www.googleapis.com/oauth2/v3/userinfo', [
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            throw new \Exception('Invalid Google access token.');
        }

        $data = $response->json();

        return [
            'provider_id' => $data['sub'],
            'email'       => $data['email'],
            'first_name'  => $data['given_name'] ?? '',
            'last_name'   => $data['family_name'] ?? '',
        ];
    }

    protected function verifyApple(string $identityToken): array
    {
        $tokenParts = explode('.', $identityToken);
        if (count($tokenParts) !== 3) {
            throw new \Exception('Invalid Apple identity token format.');
        }

        $header = json_decode(base64_decode(strtr($tokenParts[0], '-_', '+/')), true);
        if (! $header || ! isset($header['kid'])) {
            throw new \Exception('Invalid Apple token header.');
        }

        $appleKeys = Cache::remember('apple_auth_keys', 3600, function () {
            $response = Http::get('https://appleid.apple.com/auth/keys');
            if ($response->failed()) {
                throw new \Exception('Failed to fetch Apple public keys.');
            }

            return $response->json('keys');
        });

        $matchingKey = null;
        foreach ($appleKeys as $key) {
            if ($key['kid'] === $header['kid']) {
                $matchingKey = $key;
                break;
            }
        }

        if (! $matchingKey) {
            Cache::forget('apple_auth_keys');
            throw new \Exception('Apple public key not found.');
        }

        $publicKey = $this->jwkToPem($matchingKey);

        $payload = json_decode(base64_decode(strtr($tokenParts[1], '-_', '+/')), true);

        $dataToVerify = $tokenParts[0] . '.' . $tokenParts[1];
        $signature = base64_decode(strtr($tokenParts[2], '-_', '+/'));

        $algorithm = match ($header['alg'] ?? 'RS256') {
            'RS256' => OPENSSL_ALGO_SHA256,
            'RS384' => OPENSSL_ALGO_SHA384,
            'RS512' => OPENSSL_ALGO_SHA512,
            default => OPENSSL_ALGO_SHA256,
        };

        $isValid = openssl_verify($dataToVerify, $signature, $publicKey, $algorithm);

        if ($isValid !== 1) {
            throw new \Exception('Invalid Apple token signature.');
        }

        if (($payload['iss'] ?? '') !== 'https://appleid.apple.com') {
            throw new \Exception('Invalid Apple token issuer.');
        }

        if (($payload['exp'] ?? 0) < time()) {
            throw new \Exception('Apple token has expired.');
        }

        return [
            'provider_id' => $payload['sub'],
            'email'       => $payload['email'] ?? '',
            'first_name'  => '',
            'last_name'   => '',
        ];
    }

    protected function verifyFacebook(string $accessToken): array
    {
        $response = Http::get('https://graph.facebook.com/me', [
            'fields'       => 'id,first_name,last_name,email',
            'access_token' => $accessToken,
        ]);

        if ($response->failed()) {
            throw new \Exception('Invalid Facebook access token.');
        }

        $data = $response->json();

        return [
            'provider_id' => $data['id'],
            'email'       => $data['email'] ?? '',
            'first_name'  => $data['first_name'] ?? '',
            'last_name'   => $data['last_name'] ?? '',
        ];
    }

    protected function jwkToPem(array $jwk): string
    {
        $modulus = base64_decode(strtr($jwk['n'], '-_', '+/'));
        $exponent = base64_decode(strtr($jwk['e'], '-_', '+/'));

        $modulus = "\x00" . $modulus;
        $components = [
            $this->derEncodeInteger($modulus),
            $this->derEncodeInteger($exponent),
        ];

        $rsaPublicKey = $this->derEncodeSequence(implode('', $components));

        $algorithmIdentifier = $this->derEncodeSequence(
            $this->derEncodeOid("\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01") . "\x05\x00"
        );

        $bitString = "\x00" . $rsaPublicKey;
        $bitString = "\x03" . $this->derEncodeLength(strlen($bitString)) . $bitString;

        $der = $this->derEncodeSequence($algorithmIdentifier . $bitString);

        return "-----BEGIN PUBLIC KEY-----\n" .
            chunk_split(base64_encode($der), 64, "\n") .
            '-----END PUBLIC KEY-----';
    }

    protected function derEncodeInteger(string $data): string
    {
        return "\x02" . $this->derEncodeLength(strlen($data)) . $data;
    }

    protected function derEncodeSequence(string $data): string
    {
        return "\x30" . $this->derEncodeLength(strlen($data)) . $data;
    }

    protected function derEncodeOid(string $oid): string
    {
        return "\x06" . $this->derEncodeLength(strlen($oid)) . $oid;
    }

    protected function derEncodeLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        $temp = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($temp)) . $temp;
    }
}
