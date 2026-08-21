<?php

namespace Tests\Feature\Vendor;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class StripeConnectTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);
        ApiRequestor::resetTelemetry();

        parent::tearDown();
    }

    public function test_connect_succeeds_when_stripe_returns_the_accounts_v2_advisory(): void
    {
        config()->set('services.stripe.secret', 'sk_test_connect');
        $vendor = Vendor::factory()->create([
            'email' => 'restaurant@example.com',
            'restaurant_name' => 'Test Restaurant',
        ]);
        $stripe = new AdvisoryStripeHttpClient;
        ApiRequestor::setHttpClient($stripe);
        Log::spy();

        $token = $vendor->createToken('test')->plainTextToken;
        $response = $this->postJson("/api/vendor/{$vendor->id}/stripe/connect", [], [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ]);

        $response->assertOk()
            ->assertJsonPath('stripeAccountId', 'acct_advisory_success');
        $this->assertDatabaseHas('vendor_settings', [
            'vendor_id' => $vendor->id,
            'stripe_account_id' => 'acct_advisory_success',
        ]);
        $idempotencyKey = $stripe->idempotencyKey();
        $this->assertNotSame('', $idempotencyKey);
        $this->assertContains('Idempotency-Key: '.$idempotencyKey, $stripe->headers);
        Log::shouldHaveReceived('notice')->once();
    }
}

class AdvisoryStripeHttpClient implements ClientInterface
{
    /** @var string[] */
    public array $headers = [];

    public function request(
        $method,
        $absUrl,
        $headers,
        $params,
        $hasFile,
        $apiMode = 'v1',
        $maxNetworkRetries = null
    ): array {
        $this->headers = $headers;

        return [
            json_encode([
                'id' => 'acct_advisory_success',
                'object' => 'account',
                'type' => 'express',
                'charges_enabled' => false,
                'payouts_enabled' => false,
                'details_submitted' => false,
                'metadata' => ['vendor_id' => (string) $params['metadata']['vendor_id']],
            ], JSON_THROW_ON_ERROR),
            200,
            [
                'request-id' => 'req_advisory_success',
                'stripe-notice' => 'We recommend building your integration using Accounts v2. See https://docs.stripe.com/api/v2/core/accounts',
            ],
        ];
    }

    public function idempotencyKey(): string
    {
        foreach ($this->headers as $header) {
            if (str_starts_with($header, 'Idempotency-Key: ')) {
                return substr($header, strlen('Idempotency-Key: '));
            }
        }

        return '';
    }
}
