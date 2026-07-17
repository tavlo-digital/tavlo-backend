<?php

namespace Tests\Feature\Vendor;

use App\Models\TeamMember;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VendorBroadcastAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('broadcasting.default', 'pusher');
        config()->set('broadcasting.connections.pusher.key', 'test-key');
        config()->set('broadcasting.connections.pusher.secret', 'test-secret');
        config()->set('broadcasting.connections.pusher.app_id', 'test-app');
    }

    public function test_vendor_can_authorize_only_its_private_channel(): void
    {
        $vendor = Vendor::factory()->create();
        $other = Vendor::factory()->create();
        $headers = $this->headers($vendor);

        $response = $this->postJson('/api/vendor/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-vendor.{$vendor->id}",
        ], $headers)->assertOk()->assertJsonStructure(['auth']);

        $this->assertSame(
            'test-key:'.hash_hmac(
                'sha256',
                "1234.5678:private-vendor.{$vendor->id}",
                'test-secret',
            ),
            $response->json('auth'),
        );

        $this->postJson('/api/vendor/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-vendor.{$other->id}",
        ], $headers)->assertForbidden();
    }

    #[DataProvider('staffRoles')]
    public function test_staff_can_authorize_only_their_role_scoped_channel(string $role): void
    {
        $vendor = Vendor::factory()->create();
        $member = $this->member($vendor, $role);
        $headers = $this->headers($member);

        $this->postJson('/api/vendor/broadcasting/auth', [
            'socket_id' => '4321.8765',
            'channel_name' => "private-{$role}.{$member->id}",
        ], $headers)->assertOk();

        $wrongRole = $role === 'waiter' ? 'kitchen' : 'waiter';
        $this->postJson('/api/vendor/broadcasting/auth', [
            'socket_id' => '4321.8765',
            'channel_name' => "private-{$wrongRole}.{$member->id}",
        ], $headers)->assertForbidden();

        $this->postJson('/api/vendor/broadcasting/auth', [
            'socket_id' => '4321.8765',
            'channel_name' => "private-vendor.{$vendor->id}",
        ], $headers)->assertForbidden();
    }

    /** @return array<string, array{string}> */
    public static function staffRoles(): array
    {
        return [
            'waiter' => ['waiter'],
            'kitchen' => ['kitchen'],
        ];
    }

    public function test_broadcast_auth_requires_an_authenticated_active_actor(): void
    {
        $vendor = Vendor::factory()->create();

        $this->postJson('/api/vendor/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-vendor.{$vendor->id}",
        ])->assertUnauthorized();

        $waiter = $this->member($vendor, 'waiter');
        $headers = $this->headers($waiter);
        $waiter->update(['status' => 'inactive']);

        $this->postJson('/api/vendor/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-waiter.{$waiter->id}",
        ], $headers)->assertForbidden();
    }

    public function test_broadcast_auth_validates_socket_id_and_pusher_configuration(): void
    {
        $vendor = Vendor::factory()->create();
        $headers = $this->headers($vendor);

        $this->postJson('/api/vendor/broadcasting/auth', [
            'socket_id' => 'not-a-socket',
            'channel_name' => "private-vendor.{$vendor->id}",
        ], $headers)->assertUnprocessable()->assertJsonValidationErrors('socket_id');

        config()->set('broadcasting.default', 'log');

        $this->postJson('/api/vendor/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-vendor.{$vendor->id}",
        ], $headers)->assertServiceUnavailable();
    }

    private function member(Vendor $vendor, string $role): TeamMember
    {
        return TeamMember::create([
            'vendor_id' => $vendor->id,
            'name' => ucfirst($role),
            'email' => $role.uniqid().'@example.com',
            'password' => 'password',
            'role' => $role,
            'permissions' => TeamMember::defaultPermissions($role),
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    private function headers(Vendor|TeamMember $actor): array
    {
        $token = $actor->createToken('broadcast-test')->plainTextToken;

        return [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ];
    }
}
