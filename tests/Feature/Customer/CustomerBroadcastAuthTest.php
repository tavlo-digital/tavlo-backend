<?php

namespace Tests\Feature\Customer;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerBroadcastAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('broadcasting.default', 'reverb');
        config()->set('broadcasting.connections.reverb.key', 'test-key');
        config()->set('broadcasting.connections.reverb.secret', 'test-secret');
        config()->set('broadcasting.connections.reverb.app_id', 'test-app');
    }

    public function test_customer_can_authorize_only_their_private_realtime_channel(): void
    {
        $customer = Customer::factory()->create();
        $other = Customer::factory()->create();
        $token = $customer->createToken('broadcast-test')->plainTextToken;
        $headers = [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];

        $response = $this->postJson('/api/customer/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-customer.{$customer->id}",
        ], $headers)
            ->assertOk()
            ->assertJsonStructure(['auth']);
        $this->assertSame(
            'test-key:'.hash_hmac(
                'sha256',
                "1234.5678:private-customer.{$customer->id}",
                'test-secret',
            ),
            $response->json('auth'),
        );

        $this->postJson('/api/customer/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-customer.{$other->id}",
        ], $headers)->assertForbidden();
    }

    public function test_customer_broadcast_auth_requires_a_customer_token(): void
    {
        $this->postJson('/api/customer/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-customer.1',
        ])->assertUnauthorized();
    }

    public function test_customer_broadcast_auth_rejects_invalid_socket_ids(): void
    {
        $customer = Customer::factory()->create();
        $token = $customer->createToken('broadcast-test')->plainTextToken;

        $this->postJson('/api/customer/broadcasting/auth', [
            'socket_id' => 'not-a-socket',
            'channel_name' => "private-customer.{$customer->id}",
        ], [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->assertUnprocessable()->assertJsonValidationErrors('socket_id');
    }
}
