<?php

namespace Tests\Feature\Orders;

use App\Exceptions\StaffCommandConflictException;
use App\Http\Controllers\Api\Vendor\NotificationController;
use App\Http\Controllers\Api\Vendor\OrderController;
use App\Http\Controllers\Api\Vendor\StaffOrderController;
use App\Http\Controllers\Api\Vendor\TableController;
use App\Jobs\DeliverCustomerRealtime;
use App\Jobs\DeliverNotification;
use App\Jobs\ProcessStaffCommand;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Notification;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\StaffCommand;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use App\Services\StaffCommandBus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class StaffCommandTest extends TestCase
{
    use RefreshDatabase;

    private Vendor $vendor;

    private Customer $customer;

    private RestaurantTable $table;

    private TableScanSession $session;

    private Order $order;

    private TeamMember $waiter;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.staff_commands.enabled', true);
        config()->set('services.staff_commands.connection', 'redis');
        config()->set('services.staff_commands.queue', 'staffcommands');
        config()->set('services.realtime.vendor_enabled', false);

        $this->vendor = Vendor::factory()->create();
        $this->customer = Customer::factory()->create();
        $this->table = $this->vendor->restaurantTables()->create([
            'number' => 7,
            'name' => 'Table 7',
            'qr_token' => RestaurantTable::generateQrToken(),
            'is_active' => true,
            'qr_created_at' => now(),
        ]);
        $this->session = TableScanSession::create([
            'vendor_id' => $this->vendor->id,
            'restaurant_table_id' => $this->table->id,
            'customer_id' => $this->customer->id,
            'pin' => '1234',
            'status' => 'active',
            'scanned_at' => now(),
        ]);
        $this->order = Order::create([
            'order_public_id' => 'ord-'.Str::random(12),
            'customer_id' => $this->customer->id,
            'vendor_id' => $this->vendor->id,
            'table_scan_session_id' => $this->session->id,
            'status' => 'confirmed',
            'amount' => 20,
            'currency' => 'EUR',
            'payment_method' => 'cash',
            'payment_pending' => true,
            'payment_received' => false,
            'order_type' => 'dine-in',
        ]);
        $this->waiter = $this->member('waiter');
    }

    public function test_staff_mutation_returns_202_only_after_dispatch_and_does_not_mutate_inline(): void
    {
        $commandId = '0190f26e-7c87-7def-8e46-300000000001';
        $idempotencyKey = '0190f26e-7c87-7def-8e46-400000000001';
        $commands = Mockery::mock(StaffCommandBus::class);
        $commands->shouldReceive('enabled')->once()->andReturnTrue();
        $commands->shouldReceive('dispatch')->once()->withArgs(
            fn (TeamMember $actor, string $key, string $operation, array $payload, array $resources) => $actor->is($this->waiter)
                && $key === $idempotencyKey
                && $operation === 'order.confirm'
                && $payload === ['order_id' => $this->order->order_public_id]
                && $resources === ["vendor:{$this->vendor->id}:order:{$this->order->order_public_id}"]
        )->andReturn([
            'command_id' => $commandId,
            'idempotency_key' => $idempotencyKey,
            'operation' => 'order.confirm',
            'status' => 'accepted',
        ]);
        $this->app->instance(StaffCommandBus::class, $commands);

        $this->patchJson(
            "/api/vendor/orders/{$this->order->order_public_id}/confirm",
            [],
            [...$this->headers($this->waiter), 'Idempotency-Key' => $idempotencyKey],
        )->assertStatus(202)
            ->assertJsonPath('command_id', $commandId)
            ->assertJsonPath('status_url', "/api/vendor/commands/{$commandId}");

        $this->assertSame('confirmed', $this->order->fresh()->status);
    }

    public function test_staff_command_is_accepted_before_the_order_is_looked_up(): void
    {
        $missingOrderId = 'ord-does-not-exist';
        $idempotencyKey = (string) Str::uuid();
        $commandId = (string) Str::uuid();
        $commands = Mockery::mock(StaffCommandBus::class);
        $commands->shouldReceive('enabled')->once()->andReturnTrue();
        $commands->shouldReceive('dispatch')->once()->withArgs(
            fn (TeamMember $actor, string $key, string $operation, array $payload, array $resources) => $actor->is($this->waiter)
                && $key === $idempotencyKey
                && $operation === 'order.confirm'
                && $payload === ['order_id' => $missingOrderId]
                && $resources === ["vendor:{$this->vendor->id}:order:{$missingOrderId}"]
        )->andReturn([
            'command_id' => $commandId,
            'idempotency_key' => $idempotencyKey,
            'operation' => 'order.confirm',
            'status' => 'accepted',
        ]);
        $this->app->instance(StaffCommandBus::class, $commands);

        $this->patchJson(
            "/api/vendor/orders/{$missingOrderId}/confirm",
            [],
            [...$this->headers($this->waiter), 'Idempotency-Key' => $idempotencyKey],
        )->assertStatus(202)->assertJsonPath('command_id', $commandId);
    }

    public function test_terminal_idempotent_retry_includes_the_saved_worker_result(): void
    {
        $idempotencyKey = (string) Str::uuid();
        $commandId = (string) Str::uuid();
        $commands = Mockery::mock(StaffCommandBus::class);
        $commands->shouldReceive('enabled')->once()->andReturnTrue();
        $commands->shouldReceive('dispatch')->once()->andReturn([
            'command_id' => $commandId,
            'idempotency_key' => $idempotencyKey,
            'operation' => 'order.confirm',
            'status' => 'completed',
            'http_status' => 200,
            'response' => ['status' => 'waiter_confirmed'],
            'error' => null,
        ]);
        $this->app->instance(StaffCommandBus::class, $commands);

        $this->patchJson(
            "/api/vendor/orders/{$this->order->order_public_id}/confirm",
            [],
            [...$this->headers($this->waiter), 'Idempotency-Key' => $idempotencyKey],
        )->assertStatus(202)
            ->assertJsonPath('command_id', $commandId)
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('http_status', 200)
            ->assertJsonPath('response.status', 'waiter_confirmed');
    }

    public function test_waiter_table_command_is_accepted_before_the_table_is_looked_up(): void
    {
        $missingTableId = '999999';
        $idempotencyKey = (string) Str::uuid();
        $commandId = (string) Str::uuid();
        $commands = Mockery::mock(StaffCommandBus::class);
        $commands->shouldReceive('enabled')->once()->andReturnTrue();
        $commands->shouldReceive('dispatch')->once()->withArgs(
            fn (TeamMember $actor, string $key, string $operation, array $payload, array $resources) => $actor->is($this->waiter)
                && $key === $idempotencyKey
                && $operation === 'table.dismiss_call'
                && $payload === [
                    'vendor_id' => $this->vendor->vendor_public_id,
                    'table_id' => $missingTableId,
                ]
                && $resources === ["vendor:{$this->vendor->id}:table:{$missingTableId}"]
        )->andReturn([
            'command_id' => $commandId,
            'idempotency_key' => $idempotencyKey,
            'operation' => 'table.dismiss_call',
            'status' => 'accepted',
        ]);
        $this->app->instance(StaffCommandBus::class, $commands);

        $this->postJson(
            "/api/vendor/{$this->vendor->vendor_public_id}/tables/{$missingTableId}/dismiss-call",
            [],
            [...$this->headers($this->waiter), 'Idempotency-Key' => $idempotencyKey],
        )->assertStatus(202)->assertJsonPath('command_id', $commandId);
    }

    public function test_enabled_staff_mutation_requires_uuid_idempotency_key_before_dispatch(): void
    {
        $commands = Mockery::mock(StaffCommandBus::class);
        $commands->shouldReceive('enabled')->once()->andReturnTrue();
        $commands->shouldNotReceive('dispatch');
        $this->app->instance(StaffCommandBus::class, $commands);

        $this->patchJson(
            "/api/vendor/orders/{$this->order->order_public_id}/confirm",
            [],
            $this->headers($this->waiter),
        )->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');

        $this->assertSame('confirmed', $this->order->fresh()->status);
    }

    public function test_enqueue_failure_is_503_without_sync_fallback(): void
    {
        $commands = Mockery::mock(StaffCommandBus::class);
        $commands->shouldReceive('enabled')->once()->andReturnTrue();
        $commands->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('redis unavailable'));
        $this->app->instance(StaffCommandBus::class, $commands);

        $this->patchJson(
            "/api/vendor/orders/{$this->order->order_public_id}/confirm",
            [],
            [...$this->headers($this->waiter), 'Idempotency-Key' => (string) Str::uuid()],
        )->assertServiceUnavailable()->assertJsonPath('code', 'staff_commands_unavailable');

        $this->assertSame('confirmed', $this->order->fresh()->status);
    }

    public function test_owner_mutation_remains_synchronous_when_staff_commands_are_enabled(): void
    {
        $commands = Mockery::mock(StaffCommandBus::class);
        $commands->shouldNotReceive('enabled');
        $commands->shouldNotReceive('dispatch');
        $this->app->instance(StaffCommandBus::class, $commands);

        $this->patchJson(
            "/api/vendor/orders/{$this->order->order_public_id}/confirm",
            [],
            $this->headers($this->vendor),
        )->assertOk();

        $this->assertSame('waiter_confirmed', $this->order->fresh()->status);
    }

    public function test_staff_notification_read_is_an_actor_scoped_command(): void
    {
        $notification = Notification::create([
            'vendor_id' => $this->vendor->id,
            'waiter_id' => $this->waiter->id,
            'event' => 'new_order',
            'message' => 'New order',
            'read' => false,
            'is_silent' => false,
        ]);
        $idempotencyKey = (string) Str::uuid();
        $commandId = (string) Str::uuid();
        $commands = Mockery::mock(StaffCommandBus::class);
        $commands->shouldReceive('enabled')->once()->andReturnTrue();
        $commands->shouldReceive('dispatch')->once()->withArgs(
            fn (TeamMember $actor, string $key, string $operation, array $payload, array $resources) => $actor->is($this->waiter)
                && $key === $idempotencyKey
                && $operation === 'notification.read'
                && $payload === ['notification_id' => $notification->id]
                && $resources === ["vendor:{$this->vendor->id}:actor:waiter:{$this->waiter->id}:notifications"]
        )->andReturn([
            'command_id' => $commandId,
            'idempotency_key' => $idempotencyKey,
            'operation' => 'notification.read',
            'status' => 'accepted',
        ]);
        $this->app->instance(StaffCommandBus::class, $commands);

        $this->patchJson(
            "/api/vendor/notifications/{$notification->id}/read",
            [],
            [...$this->headers($this->waiter), 'Idempotency-Key' => $idempotencyKey],
        )->assertStatus(202);

        $this->assertFalse($notification->fresh()->read);
    }

    public function test_kitchen_item_batch_dispatches_one_independent_command_per_item(): void
    {
        $category = MenuCategory::create([
            'vendor_id' => $this->vendor->id,
            'name' => 'Mains',
            'slug' => 'mains',
        ]);
        $menuItem = MenuItem::create([
            'vendor_id' => $this->vendor->id,
            'menu_category_id' => $category->id,
            'name' => 'Soup',
            'price' => 8,
        ]);
        $items = collect(range(1, 2))->map(fn () => CartItem::create([
            'table_scan_session_id' => $this->session->id,
            'menu_item_id' => $menuItem->id,
            'order_id' => $this->order->id,
            'quantity' => 1,
            'received_at' => now(),
        ]));
        $kitchen = $this->member('kitchen');
        $keys = [(string) Str::uuid(), (string) Str::uuid()];

        $commands = Mockery::mock(StaffCommandBus::class);
        $commands->shouldReceive('enabled')->once()->andReturnTrue();
        $commands->shouldReceive('dispatch')->twice()->andReturnUsing(
            fn (TeamMember $actor, string $key, string $operation, array $payload) => [
                'command_id' => (string) Str::uuid(),
                'idempotency_key' => $key,
                'operation' => $operation,
                'status' => 'accepted',
                'cart_item_id' => $payload['cart_item_id'],
            ],
        );
        $this->app->instance(StaffCommandBus::class, $commands);

        $this->postJson('/api/vendor/orders/items/status-batch', [
            'commands' => $items->values()->map(fn (CartItem $item, int $index) => [
                'idempotency_key' => $keys[$index],
                'order_id' => $this->order->order_public_id,
                'cart_item_id' => $item->id,
                'status' => 'ready',
            ])->all(),
        ], $this->headers($kitchen))->assertStatus(202)
            ->assertJsonCount(2, 'commands')
            ->assertJsonPath('commands.0.idempotency_key', $keys[0])
            ->assertJsonPath('commands.1.idempotency_key', $keys[1]);

        $items->each(fn (CartItem $item) => $this->assertNull($item->fresh()->ready_at));
    }

    public function test_worker_reauthorizes_and_records_full_terminal_response(): void
    {
        $commandId = (string) Str::uuid();
        $commands = Mockery::mock(StaffCommandBus::class);
        $commands->shouldReceive('sequenceState')->once()->andReturn('ready');
        $commands->shouldReceive('markProcessing')->once()->with($commandId);
        $commands->shouldReceive('finish')->once()->withArgs(
            fn (string $id, array $sequences, string $status, array $result) => $id === $commandId
                && $status === 'completed'
                && $result['http_status'] === 200
                && ($result['response']['status'] ?? null) === 'waiter_confirmed'
        )->andReturn([
            'http_status' => 200,
            'response' => ['status' => 'waiter_confirmed'],
        ]);
        $this->app->instance(StaffCommandBus::class, $commands);

        $job = new ProcessStaffCommand(
            $commandId,
            (string) Str::uuid(),
            $this->waiter->id,
            $this->vendor->id,
            'waiter',
            'order.confirm',
            ['order_id' => $this->order->order_public_id],
            ["vendor:{$this->vendor->id}:order:{$this->order->id}" => 1],
            'en',
        );
        $this->runJob($job, $commands);

        $this->assertSame('waiter_confirmed', $this->order->fresh()->status);
        $this->assertDatabaseHas('staff_commands', [
            'command_id' => $commandId,
            'team_member_id' => $this->waiter->id,
            'status' => 'completed',
            'http_status' => 200,
        ]);
        $this->assertSame(
            'waiter_confirmed',
            StaffCommand::where('command_id', $commandId)->firstOrFail()->response['status'],
        );
    }

    public function test_waiter_served_worker_keeps_the_customer_pusher_and_notification_path(): void
    {
        Queue::fake();
        config()->set('services.realtime.customer_enabled', true);
        config()->set('services.notifications.queue_enabled', true);

        $commandId = (string) Str::uuid();
        $commands = Mockery::mock(StaffCommandBus::class);
        $commands->shouldReceive('sequenceState')->once()->andReturn('ready');
        $commands->shouldReceive('markProcessing')->once()->with($commandId);
        $commands->shouldReceive('finish')->once()->andReturn([
            'http_status' => 200,
            'response' => ['status' => 'served'],
        ]);
        $this->app->instance(StaffCommandBus::class, $commands);

        $job = new ProcessStaffCommand(
            $commandId,
            (string) Str::uuid(),
            $this->waiter->id,
            $this->vendor->id,
            'waiter',
            'order.served',
            ['order_id' => $this->order->order_public_id],
            ["vendor:{$this->vendor->id}:order:{$this->order->order_public_id}" => 1],
        );
        $this->runJob($job, $commands);
        app(DeferredCallbackCollection::class)->invoke();

        $this->assertSame('served', $this->order->fresh()->status);

        $customerDeliveryId = null;
        Queue::assertPushed(DeliverCustomerRealtime::class, function (DeliverCustomerRealtime $queued) use (&$customerDeliveryId): bool {
            $matches = $queued->type === 'table'
                && $queued->payload['event'] === 'order_updated'
                && ($queued->payload['metadata']['template'] ?? null) === 'order.served';
            if ($matches) {
                $customerDeliveryId = $queued->deliveryId;
            }

            return $matches;
        });
        Queue::assertPushed(DeliverNotification::class, fn (DeliverNotification $queued): bool => $queued->deliveryId === $customerDeliveryId && $queued->type === 'table'
        );
    }

    public function test_sequence_waits_use_a_time_deadline_instead_of_a_small_attempt_cap(): void
    {
        $job = new ProcessStaffCommand(
            (string) Str::uuid(),
            (string) Str::uuid(),
            $this->waiter->id,
            $this->vendor->id,
            'waiter',
            'order.confirm',
            ['order_id' => $this->order->order_public_id],
            ["vendor:{$this->vendor->id}:order:{$this->order->order_public_id}" => 7],
        );

        $this->assertSame(0, $job->tries);
        $this->assertGreaterThan(now()->addMinutes(50), $job->retryUntil());
    }

    public function test_worker_audit_prevents_duplicate_table_session_creation(): void
    {
        TableScanSession::query()->delete();
        $commandId = (string) Str::uuid();
        $commands = Mockery::mock(StaffCommandBus::class);
        $commands->shouldReceive('sequenceState')->once()->andReturn('ready');
        $commands->shouldReceive('markProcessing')->once();
        $commands->shouldReceive('finish')->twice()->andReturn([
            'http_status' => 201,
            'response' => ['created' => true],
        ]);
        $this->app->instance(StaffCommandBus::class, $commands);

        $job = new ProcessStaffCommand(
            $commandId,
            (string) Str::uuid(),
            $this->waiter->id,
            $this->vendor->id,
            'waiter',
            'table.create_session',
            ['vendor_id' => $this->vendor->vendor_public_id, 'table_id' => (string) $this->table->id],
            ["vendor:{$this->vendor->id}:table:{$this->table->id}" => 1],
        );

        $this->runJob($job, $commands);
        $this->runJob($job, $commands);

        $this->assertSame(1, TableScanSession::where('restaurant_table_id', $this->table->id)->count());
        $this->assertSame(1, StaffCommand::where('command_id', $commandId)->count());
    }

    public function test_worker_reauthorizes_actor_before_mutating(): void
    {
        $commandId = (string) Str::uuid();
        $this->waiter->update(['status' => 'suspended']);
        $commands = Mockery::mock(StaffCommandBus::class);
        $commands->shouldReceive('sequenceState')->once()->andReturn('ready');
        $commands->shouldNotReceive('markProcessing');
        $commands->shouldReceive('finish')->once()->withArgs(
            fn (string $id, array $sequences, string $status, array $result) => $id === $commandId && $status === 'failed' && $result['http_status'] === 403
        )->andReturn(['http_status' => 403, 'response' => ['message' => 'inactive']]);
        $this->app->instance(StaffCommandBus::class, $commands);

        $job = new ProcessStaffCommand(
            $commandId,
            (string) Str::uuid(),
            $this->waiter->id,
            $this->vendor->id,
            'waiter',
            'order.confirm',
            ['order_id' => $this->order->order_public_id],
            ["vendor:{$this->vendor->id}:order:{$this->order->id}" => 1],
        );
        $this->runJob($job, $commands);

        $this->assertSame('confirmed', $this->order->fresh()->status);
        $this->assertDatabaseHas('staff_commands', [
            'command_id' => $commandId,
            'status' => 'failed',
            'http_status' => 403,
        ]);
    }

    public function test_command_status_is_scoped_to_the_originating_actor(): void
    {
        $command = StaffCommand::create([
            'command_id' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'team_member_id' => $this->waiter->id,
            'vendor_id' => $this->vendor->id,
            'actor_role' => 'waiter',
            'operation' => 'notification.read_all',
            'status' => 'completed',
            'payload' => [],
            'resource_sequences' => [
                "vendor:{$this->vendor->id}:actor:waiter:{$this->waiter->id}:notifications" => 1,
            ],
            'http_status' => 200,
            'response' => ['message' => 'All notifications marked as read.'],
            'processed_at' => now(),
        ]);
        $otherWaiter = $this->member('waiter');
        Redis::shouldReceive('get')->twice()->andReturn(null);

        $this->getJson(
            "/api/vendor/commands/{$command->command_id}",
            $this->headers($this->waiter),
        )->assertOk()
            ->assertJsonPath('command_id', $command->command_id)
            ->assertJsonPath('response.message', 'All notifications marked as read.');

        // Each production request has a fresh guard lifecycle; mirror that
        // between two actors inside this single feature test process.
        $this->app['auth']->forgetGuards();

        $this->getJson(
            "/api/vendor/commands/{$command->command_id}",
            $this->headers($otherWaiter),
        )->assertNotFound();
    }

    public function test_bus_returns_the_existing_command_for_an_idempotent_retry(): void
    {
        Queue::fake();
        $commandId = (string) Str::uuid();
        $idempotencyKey = (string) Str::uuid();
        $resource = "vendor:{$this->vendor->id}:table:{$this->table->id}";
        $status = [
            'command_id' => $commandId,
            'idempotency_key' => $idempotencyKey,
            'team_member_id' => $this->waiter->id,
            'vendor_id' => $this->vendor->id,
            'actor_role' => 'waiter',
            'operation' => 'table.create_session',
            'status' => 'accepted',
            'resources' => [$resource],
            'resource_sequences' => [$resource => 1],
        ];

        Redis::shouldReceive('eval')->once()->andReturn(json_encode([
            'result' => 'new',
            'status' => [...$status, 'status' => 'dispatching'],
        ]));
        Redis::shouldReceive('eval')->once()->andReturn(1);
        Redis::shouldReceive('get')->once()->andReturn(json_encode($status));
        Redis::shouldReceive('eval')->once()->andReturn(json_encode([
            'result' => 'duplicate',
            'command_id' => $commandId,
        ]));
        Redis::shouldReceive('get')->once()->andReturn(json_encode($status));

        $bus = app(StaffCommandBus::class);
        $first = $bus->dispatch(
            $this->waiter,
            $idempotencyKey,
            'table.create_session',
            ['vendor_id' => $this->vendor->vendor_public_id, 'table_id' => (string) $this->table->id],
            [$resource],
        );
        $retry = $bus->dispatch(
            $this->waiter,
            $idempotencyKey,
            'table.create_session',
            ['vendor_id' => $this->vendor->vendor_public_id, 'table_id' => (string) $this->table->id],
            [$resource],
        );

        $this->assertSame($commandId, $first['command_id']);
        $this->assertSame($commandId, $retry['command_id']);
        Queue::assertPushed(ProcessStaffCommand::class, 1);
    }

    public function test_bus_rejects_idempotency_key_reuse_for_a_different_command(): void
    {
        Redis::shouldReceive('eval')->once()->andReturn(json_encode([
            'result' => 'conflict',
            'command_id' => (string) Str::uuid(),
        ]));

        $this->expectException(StaffCommandConflictException::class);
        app(StaffCommandBus::class)->dispatch(
            $this->waiter,
            (string) Str::uuid(),
            'order.confirm',
            ['order_id' => $this->order->order_public_id],
            ["vendor:{$this->vendor->id}:order:{$this->order->id}"],
        );
    }

    private function runJob(ProcessStaffCommand $job, StaffCommandBus $commands): void
    {
        $job->handle(
            $commands,
            $this->app->make(OrderController::class),
            $this->app->make(TableController::class),
            $this->app->make(StaffOrderController::class),
            $this->app->make(NotificationController::class),
        );
    }

    private function member(string $role): TeamMember
    {
        return TeamMember::create([
            'vendor_id' => $this->vendor->id,
            'name' => ucfirst($role),
            'email' => $role.Str::random(8).'@example.com',
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
}
