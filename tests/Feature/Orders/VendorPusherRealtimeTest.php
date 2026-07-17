<?php

namespace Tests\Feature\Orders;

use App\Events\OperationalRealtimeNotification;
use App\Jobs\DeliverOperationalNotification;
use App\Jobs\DeliverOperationalRealtime;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VendorPusherRealtimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_notifications_use_the_dedicated_queue(): void
    {
        Queue::fake();
        $vendor = Vendor::factory()->create();

        NotificationService::notifyOperations(
            $vendor->id,
            'order_confirmed',
            'A new order was confirmed.',
            [NotificationService::VENDOR],
            ['resources' => ['orders', 'notifications']],
        );

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('notifications', 0);

        $this->invokeDeferredCallbacks();

        Queue::assertPushedOn(
            'vendor-notifications',
            DeliverOperationalNotification::class,
            fn (DeliverOperationalNotification $job): bool => $job->type === 'operations'
                && $job->payload['vendor_id'] === $vendor->id,
        );
        Queue::assertNotPushed(\App\Jobs\DeliverNotification::class);
    }

    public function test_table_customer_persistence_keeps_its_existing_path_while_operations_are_isolated(): void
    {
        Queue::fake();
        config()->set('services.notifications.queue_enabled', false);
        $vendor = Vendor::factory()->create();
        $customer = Customer::factory()->create();
        $table = RestaurantTable::create([
            'vendor_id' => $vendor->id,
            'number' => 4,
            'name' => 'Table 4',
            'qr_token' => 'table-4-token',
            'is_active' => true,
        ]);
        TableScanSession::create([
            'vendor_id' => $vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $customer->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        NotificationService::notifyTableCustomers(
            $table->id,
            'order_updated',
            'Order changed.',
            ['template' => 'order.waiter_confirmed'],
        );

        $this->assertDatabaseHas('notifications', [
            'customer_id' => $customer->id,
            'event' => 'order_updated',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'vendor_id' => $vendor->id,
            'customer_id' => null,
        ]);

        $this->invokeDeferredCallbacks();

        Queue::assertPushedOn(
            'vendor-notifications',
            DeliverOperationalNotification::class,
            fn (DeliverOperationalNotification $job): bool => $job->type === 'table'
                && $job->deliveryId === Notification::query()
                    ->where('customer_id', $customer->id)
                    ->sole()
                    ->metadata['event_id'],
        );
    }

    public function test_persisted_operational_rows_are_enqueued_for_realtime_once_they_exist(): void
    {
        Queue::fake();
        config()->set('services.realtime.vendor_enabled', true);
        $vendor = Vendor::factory()->create();
        $waiter = $this->member($vendor, 'waiter');
        $kitchen = $this->member($vendor, 'kitchen');

        $job = new DeliverOperationalNotification('operational-delivery-1', 'operations', [
            'vendor_id' => $vendor->id,
            'event' => 'order_confirmed',
            'message' => 'A new order was confirmed.',
            'audiences' => [
                NotificationService::VENDOR,
                NotificationService::WAITER,
                NotificationService::KITCHEN,
            ],
            'metadata' => [
                'event_version' => 123456,
                'resources' => ['orders', 'tables', 'dashboard', 'notifications'],
                'order_snapshots' => [['order_id' => 41, 'status' => 'confirmed']],
            ],
            'silent' => false,
            'created_at' => now()->toISOString(),
        ]);

        $job->handle();
        $job->handle();

        $this->assertDatabaseCount('notifications', 3);
        $this->assertDatabaseHas('notifications', [
            'delivery_key' => 'operational-delivery-1:vendor:'.$vendor->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'delivery_key' => 'operational-delivery-1:waiter:'.$waiter->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'delivery_key' => 'operational-delivery-1:kitchen:'.$kitchen->id,
        ]);
        Queue::assertNothingPushed();

        $this->invokeDeferredCallbacks();

        Queue::assertPushedOn(
            'vendor-realtime',
            DeliverOperationalRealtime::class,
            fn (DeliverOperationalRealtime $realtime): bool => $realtime->deliveryId === 'operational-delivery-1'
                && Notification::query()
                    ->where('delivery_key', 'like', 'operational-delivery-1:%')
                    ->count() === 3,
        );
        Queue::assertPushed(DeliverOperationalRealtime::class, 1);
    }

    public function test_realtime_job_broadcasts_persisted_actor_scoped_payloads(): void
    {
        Event::fake([OperationalRealtimeNotification::class]);
        config()->set('services.realtime.vendor_enabled', false);
        $vendor = Vendor::factory()->create();
        $waiter = $this->member($vendor, 'waiter');
        $kitchen = $this->member($vendor, 'kitchen');

        (new DeliverOperationalNotification('operational-delivery-2', 'operations', [
            'vendor_id' => $vendor->id,
            'event' => 'order_confirmed',
            'message' => 'A new order was confirmed.',
            'audiences' => [
                NotificationService::VENDOR,
                NotificationService::WAITER,
                NotificationService::KITCHEN,
            ],
            'metadata' => [
                'event_version' => 654321,
                'resources' => ['orders', 'tables'],
                'order_snapshots' => [['order_id' => 77, 'status' => 'confirmed']],
            ],
            'silent' => false,
            'created_at' => now()->toISOString(),
        ]))->handle();

        (new DeliverOperationalRealtime('operational-delivery-2'))->handle();

        Event::assertDispatchedTimes(OperationalRealtimeNotification::class, 3);
        Event::assertDispatched(
            OperationalRealtimeNotification::class,
            function (OperationalRealtimeNotification $event) use ($waiter): bool {
                $channel = $event->broadcastOn()[0];

                return $event->recipientRole === 'waiter'
                    && $event->recipientId === $waiter->id
                    && $channel->name === "private-waiter.{$waiter->id}"
                    && is_int($event->payload['id'])
                    && $event->payload['user_role'] === 'waiter'
                    && $event->payload['metadata']['event_id'] === 'operational-delivery-2'
                    && $event->payload['metadata']['resources'] === ['orders', 'tables']
                    && $event->payload['metadata']['order_snapshots'][0]['order_id'] === 77;
            },
        );
        Event::assertDispatched(
            OperationalRealtimeNotification::class,
            fn (OperationalRealtimeNotification $event): bool => $event->recipientRole === 'vendor'
                && $event->recipientId === $vendor->id
                && $event->broadcastOn()[0]->name === "private-vendor.{$vendor->id}",
        );
        Event::assertDispatched(
            OperationalRealtimeNotification::class,
            fn (OperationalRealtimeNotification $event): bool => $event->recipientRole === 'kitchen'
                && $event->recipientId === $kitchen->id
                && $event->broadcastOn()[0]->name === "private-kitchen.{$kitchen->id}",
        );
    }

    public function test_terminal_command_event_targets_only_the_initiating_actor(): void
    {
        config()->set('services.realtime.vendor_enabled', false);
        $vendor = Vendor::factory()->create();
        $waiter = $this->member($vendor, 'waiter');
        $this->member($vendor, 'waiter');

        NotificationService::notifyOperationalActor(
            $vendor->id,
            NotificationService::WAITER,
            $waiter->id,
            'staff_command_completed',
            'Command completed.',
            [
                'command_id' => 'command-1',
                'command_status' => 'completed',
                'resources' => ['orders'],
            ],
        );
        $this->invokeDeferredCallbacks();

        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'vendor_id' => $vendor->id,
            'waiter_id' => $waiter->id,
            'event' => 'staff_command_completed',
            'is_silent' => true,
            'read' => true,
        ]);
        $this->assertSame(
            'command-1',
            Notification::query()->sole()->metadata['command_id'],
        );
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

    private function invokeDeferredCallbacks(): void
    {
        app(DeferredCallbackCollection::class)->invoke();
    }
}
