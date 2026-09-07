<?php

namespace Tests\Feature\Orders;

use App\Jobs\DeliverNotification;
use App\Jobs\DeliverOperationalNotification;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VendorNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private TeamMember $waiter;

    private TeamMember $kitchen;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.staff_commands.enabled', false);

        $this->vendor = Vendor::factory()->create();
        $this->waiter = $this->member('waiter');
        $this->kitchen = $this->member('kitchen');
    }

    public function test_operational_notifications_are_scoped_to_each_actor(): void
    {
        Queue::fake();
        Notification::create([
            'customer_id' => Customer::factory()->create()->id,
            'vendor_id' => $this->vendor->id,
            'event' => 'customer_only',
            'message' => 'Customer-only message',
        ]);

        NotificationService::notifyOperations(
            $this->vendor->id,
            'order_confirmed',
            'A new order was confirmed.',
            [NotificationService::VENDOR, NotificationService::WAITER, NotificationService::KITCHEN],
            [
                'resources' => ['orders', 'tables', 'dashboard', 'notifications'],
                'template' => 'staff.order_confirmed',
                'order_id' => 41,
                'order_number' => 41,
                'table_label' => 'Table 7',
                'severity' => 'urgent',
                'sound' => 'new_order',
            ],
        );
        $this->invokeDeferredCallbacks();
        Queue::pushed(DeliverOperationalNotification::class)->sole()->handle();

        $vendorResponse = $this->getJson('/api/vendor/notifications', $this->headers($this->vendor));
        $vendorResponse->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.event', 'order_confirmed')
            ->assertJsonPath('unread_count', 1);

        $waiterResponse = $this->getJson('/api/vendor/notifications', $this->headers($this->waiter));
        $waiterResponse->assertOk()
            ->assertJsonCount(1, 'notifications')
            ->assertJsonPath('notifications.0.metadata.resources.0', 'orders');

        $kitchenResponse = $this->getJson('/api/vendor/notifications', $this->headers($this->kitchen));
        $kitchenResponse->assertOk()->assertJsonCount(1, 'notifications');

        $waiterNotificationId = $waiterResponse->json('notifications.0.id');
        $this->patchJson("/api/vendor/notifications/{$waiterNotificationId}/read", [], $this->headers($this->waiter))
            ->assertOk();
        $this->postJson('/api/vendor/notifications/read-all', [], $this->headers($this->vendor))
            ->assertOk();

        $this->getJson('/api/vendor/notifications', $this->headers($this->waiter))
            ->assertJsonPath('unread_count', 0);
        $this->getJson('/api/vendor/notifications', $this->headers($this->vendor))
            ->assertJsonPath('unread_count', 0);
    }

    public function test_table_event_reads_by_table_for_staff_and_by_name_for_guests(): void
    {
        Queue::fake();

        $table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'number' => 7,
            'name' => 'Table 7',
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);
        $customer = Customer::factory()->create();
        TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $customer->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        NotificationService::notifyTableCustomers(
            $table->id,
            'payment_updated',
            'Guest L2K5YI requested cash payment.',
            ['template' => 'payment.cash_requested'],
            true,
            'Table 7 requested cash payment.',
        );
        $this->invokeDeferredCallbacks();
        foreach (Queue::pushed(DeliverOperationalNotification::class) as $job) {
            $job->handle();
        }
        foreach (Queue::pushed(DeliverNotification::class) as $job) {
            $job->handle();
        }

        // The waiter is told where to go; the guest still sees who acted.
        $this->assertDatabaseHas('notifications', [
            'vendor_id' => $this->vendor->id,
            'customer_id' => null,
            'message' => 'Table 7 requested cash payment.',
        ]);
        $this->assertDatabaseHas('notifications', [
            'customer_id' => $customer->id,
            'message' => 'Guest L2K5YI requested cash payment.',
        ]);
    }

    public function test_table_event_without_staff_wording_still_reaches_staff(): void
    {
        Queue::fake();

        $table = RestaurantTable::create([
            'vendor_id' => $this->vendor->id,
            'number' => 9,
            'name' => 'Table 9',
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);
        TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => Customer::factory()->create()->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        // Jobs queued before the staff wording existed carry only one message.
        NotificationService::notifyTableCustomers(
            $table->id,
            'payment_updated',
            'A guest requested cash payment.',
            ['template' => 'payment.cash_requested'],
        );
        $this->invokeDeferredCallbacks();
        foreach (Queue::pushed(DeliverOperationalNotification::class) as $job) {
            $job->handle();
        }

        $this->assertDatabaseHas('notifications', [
            'vendor_id' => $this->vendor->id,
            'customer_id' => null,
            'message' => 'A guest requested cash payment.',
        ]);
    }

    public function test_silent_notifications_are_realtime_only_and_not_unread(): void
    {
        Queue::fake();
        NotificationService::notifyOperations(
            $this->vendor->id,
            'cart_updated',
            'Cart changed.',
            [NotificationService::VENDOR, NotificationService::WAITER],
            ['resources' => ['orders', 'tables']],
            true,
        );
        $this->invokeDeferredCallbacks();
        Queue::pushed(DeliverOperationalNotification::class)->sole()->handle();

        $this->getJson('/api/vendor/notifications', $this->headers($this->vendor))
            ->assertOk()
            ->assertJsonCount(0, 'notifications')
            ->assertJsonPath('unread_count', 0);

        $this->assertDatabaseHas('notifications', [
            'vendor_id' => $this->vendor->id,
            'event' => 'cart_updated',
            'is_silent' => true,
            'read' => true,
        ]);
    }

    private function member(string $role): TeamMember
    {
        return TeamMember::create([
            'vendor_id' => $this->vendor->id,
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
        $token = $actor->createToken('test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    private function invokeDeferredCallbacks(): void
    {
        app(DeferredCallbackCollection::class)->invoke();
    }
}
