<?php

namespace Tests\Feature\Customer;

use App\Events\CustomerRealtimeNotification;
use App\Jobs\DeliverCustomerRealtime;
use App\Jobs\DeliverNotification;
use App\Jobs\RecordCustomerSessionActivity;
use App\Models\Customer;
use App\Models\CustomerSessionActivity;
use App\Models\Notification;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QueuedSideEffectsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_fan_out_is_dispatched_to_the_notification_queue(): void
    {
        Queue::fake();
        config()->set('services.notifications.queue_enabled', true);

        [$table] = $this->activeTableSession();

        NotificationService::notifyTableCustomers(
            $table->id,
            'cart_updated',
            'Cart changed.',
            ['template' => 'cart.item_updated'],
            false,
        );

        $this->assertDatabaseCount('notifications', 0);
        $this->invokeDeferredCallbacks();
        Queue::assertPushedOn('notifications', DeliverNotification::class);
    }

    public function test_realtime_and_notification_jobs_share_an_event_id_and_are_enqueued_after_response(): void
    {
        Queue::fake();
        config()->set('services.notifications.queue_enabled', true);
        config()->set('services.realtime.customer_enabled', true);

        [$table] = $this->activeTableSession();

        NotificationService::notifyTableCustomers(
            $table->id,
            'order_updated',
            'Sharing changed.',
            ['template' => 'order.sharing_updated'],
            false,
        );

        Queue::assertNothingPushed();
        $this->invokeDeferredCallbacks();

        $realtimeDeliveryId = null;
        Queue::assertPushedOn('realtime', DeliverCustomerRealtime::class, function (DeliverCustomerRealtime $job) use (&$realtimeDeliveryId): bool {
            $realtimeDeliveryId = $job->deliveryId;

            return $job->payload['metadata']['template'] === 'order.sharing_updated';
        });
        Queue::assertPushedOn('notifications', DeliverNotification::class, function (DeliverNotification $job) use (&$realtimeDeliveryId): bool {
            return $job->deliveryId === $realtimeDeliveryId;
        });
    }

    public function test_realtime_job_broadcasts_once_to_all_active_table_customers(): void
    {
        Event::fake([CustomerRealtimeNotification::class]);
        [$table, $firstSession] = $this->activeTableSession();
        $secondCustomer = Customer::factory()->create();
        TableScanSession::create([
            'vendor_id' => $firstSession->vendor_id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $secondCustomer->id,
            'pin' => '2345',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        (new DeliverCustomerRealtime('realtime-delivery-1', 'table', [
            'restaurant_table_id' => $table->id,
            'event' => 'payment_updated',
            'message' => 'Payment changed.',
            'metadata' => ['state_patch' => ['id' => 'patch-1']],
            'created_at' => now()->toISOString(),
        ]))->handle();

        Event::assertDispatched(CustomerRealtimeNotification::class, function (CustomerRealtimeNotification $event) use ($firstSession, $secondCustomer): bool {
            $ids = collect($event->customerIds)->sort()->values()->all();

            return $event->deliveryId === 'realtime-delivery-1'
                && $event->event === 'payment_updated'
                && $event->metadata['state_patch']['id'] === 'patch-1'
                && $ids === collect([$firstSession->customer_id, $secondCustomer->id])->sort()->values()->all();
        });
    }

    public function test_notification_delivery_is_idempotent_for_each_recipient(): void
    {
        [$table, $firstSession] = $this->activeTableSession();
        $secondCustomer = Customer::factory()->create();
        TableScanSession::create([
            'vendor_id' => $firstSession->vendor_id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $secondCustomer->id,
            'pin' => '2345',
            'status' => 'active',
            'scanned_at' => now(),
        ]);

        $job = new DeliverNotification('delivery-test-1', 'table', [
            'restaurant_table_id' => $table->id,
            'event' => 'order_updated',
            'message' => 'Order changed.',
            'metadata' => [
                'template' => 'order.sharing_updated',
                'event_version' => 123456,
            ],
            'notify_operations' => false,
        ]);

        $job->handle();
        $job->handle();

        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(2, Notification::query()->distinct()->count('delivery_key'));
        $this->assertSame(
            ['delivery-test-1'],
            Notification::query()->get()->map(fn (Notification $notification) => $notification->metadata['event_id'])->unique()->values()->all(),
        );
    }

    public function test_session_activity_is_queued_after_the_customer_response(): void
    {
        Queue::fake();
        config()->set('services.session_activity.queue_enabled', true);

        [, $session, $customer] = $this->activeTableSession();
        $token = $customer->createToken('test')->plainTextToken;

        $this->getJson('/api/customer/me', [
            'Authorization' => "Bearer {$token}",
            'Accept' => 'application/json',
        ])->assertOk();

        $this->assertDatabaseCount('customer_session_activities', 0);
        Queue::assertPushedOn('activity', RecordCustomerSessionActivity::class, function (RecordCustomerSessionActivity $job) use ($customer): bool {
            return $job->customerId === $customer->id
                && $job->endpoint === 'api/customer/me'
                && $job->method === 'GET';
        });
    }

    public function test_activity_job_preserves_the_request_time_if_session_closes_before_delivery(): void
    {
        [, $session, $customer] = $this->activeTableSession();
        $occurredAt = now()->subSecond()->startOfSecond();
        $session->update([
            'status' => 'closed',
            'closed_at' => $occurredAt->copy()->addSecond(),
        ]);

        (new RecordCustomerSessionActivity(
            $customer->id,
            'api/customer/table/close',
            'POST',
            $occurredAt->toISOString(),
        ))->handle();

        $this->assertDatabaseHas('customer_session_activities', [
            'table_scan_session_id' => $session->id,
            'customer_id' => $customer->id,
            'endpoint' => 'api/customer/table/close',
            'method' => 'POST',
            'created_at' => $occurredAt->toDateTimeString(),
        ]);
    }

    public function test_old_session_activity_rows_are_pruned_by_retention(): void
    {
        [, $session, $customer] = $this->activeTableSession();
        config()->set('services.session_activity.retention_days', 30);

        $old = CustomerSessionActivity::create([
            'table_scan_session_id' => $session->id,
            'customer_id' => $customer->id,
            'endpoint' => 'api/customer/cart',
            'method' => 'GET',
        ]);
        $old->timestamps = false;
        $old->forceFill([
            'created_at' => now()->subDays(31),
            'updated_at' => now()->subDays(31),
        ])->save();

        CustomerSessionActivity::create([
            'table_scan_session_id' => $session->id,
            'customer_id' => $customer->id,
            'endpoint' => 'api/customer/me',
            'method' => 'GET',
        ]);

        $this->artisan('session-activities:prune')->assertSuccessful();

        $this->assertDatabaseMissing('customer_session_activities', ['id' => $old->id]);
        $this->assertDatabaseCount('customer_session_activities', 1);
    }

    /** @return array{RestaurantTable, TableScanSession, Customer} */
    private function activeTableSession(): array
    {
        $vendor = Vendor::factory()->create();
        $customer = Customer::factory()->create();
        $table = RestaurantTable::create([
            'vendor_id' => $vendor->id,
            'number' => 2,
            'name' => 'Table 2',
            'qr_token' => 'table-2-token',
            'is_active' => true,
        ]);
        $session = TableScanSession::create([
            'vendor_id' => $vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $customer->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now()->subMinute(),
        ]);

        return [$table, $session, $customer];
    }

    private function invokeDeferredCallbacks(): void
    {
        app(DeferredCallbackCollection::class)->invoke();
    }
}
