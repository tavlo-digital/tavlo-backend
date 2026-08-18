<?php

namespace Tests\Feature\Orders;

use App\Events\OperationalRealtimeNotification;
use App\Jobs\DeliverOperationalNotification;
use App\Jobs\DeliverOperationalRealtime;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\CustomerSessionActivity;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use App\Services\KitchenOrderReleaseService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class KitchenOffPremiseReleaseTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Customer $customer;

    private MenuItem $menuItem;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-09 10:00:00');
        $this->vendor = Vendor::factory()->create();
        $this->customer = Customer::factory()->create();
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mains',
            'slug' => 'kitchen-release-'.$this->vendor->id,
        ]);
        $this->menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Pasta',
            'price' => 12.50,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_scheduled_order_is_visible_in_kitchen_pickup_schedule_before_twenty_minute_window(): void
    {
        [$order] = $this->offPremiseOrder(now()->addMinutes(21));
        $waiter = $this->member('waiter');
        $kitchen = $this->member('kitchen');

        $this->getJson($this->ordersUrl(), $this->headers($waiter))
            ->assertOk()
            ->assertJsonCount(1, 'takeaway')
            ->assertJsonPath('takeaway.0.id', (string) $order->id);

        $this->app['auth']->forgetGuards();
        $this->getJson($this->ordersUrl(), $this->headers($kitchen))
            ->assertOk()
            ->assertJsonCount(1, 'takeaway')
            ->assertJsonPath('takeaway.0.id', (string) $order->id)
            ->assertJsonPath('takeaway.0.kitchenReleasedAt', null);

        $this->artisan('kitchen-orders:release-scheduled')
            ->expectsOutput('Released 0 order(s) to kitchen.')
            ->assertSuccessful();
        $this->assertNull($order->fresh()->kitchen_released_at);

        Carbon::setTestNow(now()->addMinute());
        $this->artisan('kitchen-orders:release-scheduled')
            ->expectsOutput('Released 1 order(s) to kitchen.')
            ->assertSuccessful();

        $this->assertTrue($order->fresh()->kitchen_released_at?->equalTo(now()));
        $this->app['auth']->forgetGuards();
        $this->getJson($this->ordersUrl(), $this->headers($kitchen))
            ->assertOk()
            ->assertJsonCount(1, 'takeaway')
            ->assertJsonPath('takeaway.0.kitchenReleasedAt', now()->toISOString());
    }

    public function test_due_release_delivers_one_kitchen_scoped_pusher_event_with_complete_snapshot(): void
    {
        Queue::fake();
        Event::fake([OperationalRealtimeNotification::class]);
        config()->set('services.realtime.vendor_enabled', true);
        $kitchen = $this->member('kitchen');
        $waiter = $this->member('waiter');
        [$order] = $this->offPremiseOrder(now()->addMinutes(20));

        $released = app(KitchenOrderReleaseService::class)->releaseIfDue($order);
        $this->assertTrue($released);
        $this->assertFalse(app(KitchenOrderReleaseService::class)->releaseIfDue($order->fresh()));

        app(DeferredCallbackCollection::class)->invoke();
        $delivery = Queue::pushed(DeliverOperationalNotification::class)->sole();
        $this->assertSame(['kitchen'], $delivery->payload['audiences']);
        $this->assertSame((string) $order->id, (string) $delivery->payload['metadata']['order']['id']);
        $this->assertSame(now()->toISOString(), $delivery->payload['metadata']['order']['kitchenReleasedAt']);

        $delivery->handle();
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseHas('notifications', [
            'kitchen_id' => $kitchen->id,
            'event' => 'order_confirmed',
        ]);
        $this->assertDatabaseMissing('notifications', ['waiter_id' => $waiter->id]);

        (new DeliverOperationalRealtime($delivery->deliveryId))->handle();
        Event::assertDispatched(
            OperationalRealtimeNotification::class,
            fn (OperationalRealtimeNotification $event): bool => $event->recipientRole === 'kitchen'
                && $event->recipientId === $kitchen->id
                && $event->payload['metadata']['order']['kitchenReleasedAt'] === now()->toISOString(),
        );
    }

    public function test_future_pickup_is_pushed_silently_to_the_kitchen_schedule(): void
    {
        Queue::fake();
        Event::fake([OperationalRealtimeNotification::class]);
        config()->set('services.realtime.vendor_enabled', true);
        $kitchen = $this->member('kitchen');
        [$order] = $this->offPremiseOrder(now()->addHour());

        app(KitchenOrderReleaseService::class)->notifyPaidOffPremiseOrder(
            $order,
            'customer',
            $this->customer->id,
        );
        app(DeferredCallbackCollection::class)->invoke();

        $delivery = Queue::pushed(DeliverOperationalNotification::class)
            ->first(fn (DeliverOperationalNotification $job): bool => $job->payload['audiences'] === ['kitchen']);

        $this->assertInstanceOf(DeliverOperationalNotification::class, $delivery);
        $this->assertSame('order_scheduled', $delivery->payload['event']);
        $this->assertTrue($delivery->payload['silent']);
        $this->assertNull($delivery->payload['metadata']['order']['kitchenReleasedAt']);

        $delivery->handle();
        $this->assertDatabaseHas('notifications', [
            'kitchen_id' => $kitchen->id,
            'event' => 'order_scheduled',
            'is_silent' => true,
        ]);

        (new DeliverOperationalRealtime($delivery->deliveryId))->handle();
        Event::assertDispatched(
            OperationalRealtimeNotification::class,
            fn (OperationalRealtimeNotification $event): bool => $event->recipientRole === 'kitchen'
                && $event->recipientId === $kitchen->id
                && $event->payload['is_silent'] === true
                && $event->payload['metadata']['order']['kitchenReleasedAt'] === null,
        );
    }

    public function test_asap_paid_order_is_released_immediately_and_waiter_can_mark_it_picked_up(): void
    {
        config()->set('services.staff_commands.enabled', false);
        [$order, $item] = $this->offPremiseOrder(null, ready: true);
        $waiter = $this->member('waiter');

        $this->assertTrue(app(KitchenOrderReleaseService::class)->releaseIfDue($order));
        $this->assertNotNull($order->fresh()->kitchen_released_at);

        $response = $this->patchJson(
            "/api/vendor/orders/{$order->id}/picked-up",
            [],
            $this->headers($waiter),
        );

        $response->assertOk()
            ->assertJsonPath('status', Order::STATUS_PICKED_UP)
            ->assertJsonPath('pickupStatus', 'picked-up')
            ->assertJsonPath('items.0.status', 'picked_up');
        $this->assertNotNull($order->fresh()->picked_up_at);
        $this->assertNotNull($item->fresh()->picked_up_at);
    }

    public function test_waiter_cannot_pick_up_an_order_before_every_item_is_ready(): void
    {
        config()->set('services.staff_commands.enabled', false);
        [$order, $item] = $this->offPremiseOrder(null);

        $this->patchJson(
            "/api/vendor/orders/{$order->id}/picked-up",
            [],
            $this->headers($this->member('waiter')),
        )->assertConflict()
            ->assertJsonPath('message', 'All order items must be ready before pickup.');

        $this->assertNull($order->fresh()->picked_up_at);
        $this->assertNull($item->fresh()->picked_up_at);
    }

    public function test_completed_pickup_group_closes_immediately_and_realtime_targets_only_its_orders(): void
    {
        Queue::fake();
        [$order, $item] = $this->offPremiseOrder(null, ready: true);
        $pickedUpAt = now();
        $order->update(['status' => Order::STATUS_PICKED_UP, 'picked_up_at' => $pickedUpAt]);
        $item->update(['picked_up_at' => $pickedUpAt]);
        CustomerSessionActivity::create([
            'table_scan_session_id' => $order->table_scan_session_id,
            'customer_id' => $this->customer->id,
            'endpoint' => '/api/customer/table/history',
            'method' => 'GET',
        ]);

        $this->artisan('table-sessions:close-stale')->assertSuccessful();

        $this->assertDatabaseHas('table_scan_sessions', [
            'id' => $order->table_scan_session_id,
            'status' => 'closed',
        ]);
        app(DeferredCallbackCollection::class)->invoke();
        Queue::assertPushed(
            DeliverOperationalNotification::class,
            fn (DeliverOperationalNotification $job): bool => $job->payload['event'] === 'table_session_changed'
                && ($job->payload['metadata']['table_action'] ?? null) === 'closed'
                && array_key_exists('table_id', $job->payload['metadata'])
                && $job->payload['metadata']['table_id'] === null
                && ($job->payload['metadata']['order_ids'] ?? []) === [$order->id],
        );
    }

    public function test_pickup_list_drops_orders_collected_on_an_earlier_day(): void
    {
        [$today] = $this->offPremiseOrder(null, ready: true);
        $today->update(['status' => Order::STATUS_PICKED_UP, 'picked_up_at' => now()]);

        [$yesterday] = $this->offPremiseOrder(null, ready: true);
        $yesterday->update([
            'status' => Order::STATUS_PICKED_UP,
            'picked_up_at' => now()->subDay(),
        ]);
        $yesterday->forceFill(['created_at' => now()->subDay()])->saveQuietly();

        $this->getJson($this->ordersUrl(), $this->headers($this->member('waiter')))
            ->assertOk()
            ->assertJsonCount(1, 'takeaway')
            ->assertJsonPath('takeaway.0.id', (string) $today->id);
    }

    public function test_pickup_list_keeps_uncollected_orders_regardless_of_age(): void
    {
        [$stale] = $this->offPremiseOrder(null, ready: true);
        $stale->forceFill(['created_at' => now()->subDays(3)])->saveQuietly();

        // A pickup booked for tomorrow must survive today's cutoff too.
        [$scheduled] = $this->offPremiseOrder(now()->addDay());

        $response = $this->getJson($this->ordersUrl(), $this->headers($this->member('waiter')))
            ->assertOk()
            ->assertJsonCount(2, 'takeaway');

        $returnedIds = collect($response->json('takeaway'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing(
            [(string) $stale->id, (string) $scheduled->id],
            $returnedIds,
        );
    }

    /** @return array{Order, CartItem} */
    private function offPremiseOrder(?CarbonInterface $scheduledFor, bool $ready = false): array
    {
        $session = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => null,
            'customer_id' => $this->customer->id,
            'type' => 'pickup',
            'pin' => (string) random_int(1000, 9999),
            'status' => 'active',
            'scanned_at' => now(),
            'scheduled_for' => $scheduledFor,
        ]);
        $order = Order::create([
            'order_public_id' => 'ord-'.uniqid(),
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $session->id,
            'status' => Order::STATUS_CONFIRMED,
            'confirmed_at' => now(),
            'amount' => 12.50,
            'currency' => 'EUR',
            'payment_method' => 'stripe',
            'payment_received' => true,
            'payment_confirmed_at' => now(),
            'order_type' => 'pickup',
        ]);
        $item = CartItem::create([
            'table_scan_session_id' => $session->id,
            'order_id' => $order->id,
            'menu_item_id' => $this->menuItem->id,
            'quantity' => 1,
            'received_at' => now(),
            'preparing_start_at' => $ready ? now() : null,
            'ready_at' => $ready ? now() : null,
        ]);

        return [$order, $item];
    }

    private function member(string $role): TeamMember
    {
        return TeamMember::create([
            'vendor_id' => $this->vendor->id,
            'name' => ucfirst($role),
            'email' => $role.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'permissions' => TeamMember::defaultPermissions($role),
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    /** @return array<string, string> */
    private function headers(TeamMember $member): array
    {
        $token = $member->createToken('test', ['role:team_member', "role:{$member->role}"])->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }

    private function ordersUrl(): string
    {
        return "/api/vendor/{$this->vendor->vendor_public_id}/orders";
    }
}
