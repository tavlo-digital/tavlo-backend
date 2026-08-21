<?php

namespace Tests\Feature\Customer;

use App\Http\Controllers\Api\Customer\CartController;
use App\Jobs\ProcessCustomerCommand;
use App\Services\CustomerCommandBus;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class CustomerCommandWorkerTest extends TestCase
{
    private function bus(): CustomerCommandBus
    {
        return app(CustomerCommandBus::class);
    }

    public function test_worker_is_alive_when_heartbeat_is_fresh(): void
    {
        config()->set('services.customer_commands.worker_heartbeat_max_age', 15);
        Redis::shouldReceive('get')->once()->andReturn((string) time());

        $this->assertTrue($this->bus()->workerAlive());
    }

    public function test_worker_is_not_alive_when_heartbeat_is_stale(): void
    {
        config()->set('services.customer_commands.worker_heartbeat_max_age', 15);
        Redis::shouldReceive('get')->once()->andReturn((string) (time() - 60));

        $this->assertFalse($this->bus()->workerAlive());
    }

    public function test_worker_is_not_alive_when_heartbeat_is_missing(): void
    {
        config()->set('services.customer_commands.worker_heartbeat_max_age', 15);
        Redis::shouldReceive('get')->once()->andReturn(null);

        $this->assertFalse($this->bus()->workerAlive());
    }

    public function test_worker_is_not_alive_when_redis_is_unreachable(): void
    {
        config()->set('services.customer_commands.worker_heartbeat_max_age', 15);
        Redis::shouldReceive('get')->once()->andThrow(new \RuntimeException('connection refused'));

        $this->assertFalse($this->bus()->workerAlive());
    }

    public function test_liveness_guard_can_be_disabled(): void
    {
        // max_age = 0 disables the guard: always considered alive, Redis untouched.
        config()->set('services.customer_commands.worker_heartbeat_max_age', 0);
        Redis::shouldReceive('get')->never();

        $this->assertTrue($this->bus()->workerAlive());
    }

    public function test_ordering_releases_do_not_exhaust_the_command_attempt_budget(): void
    {
        $job = new ProcessCustomerCommand(
            '0190f26e-7c87-7def-8e46-333333333333',
            2,
            10,
            20,
            'order.share',
            ['order_id' => 30, 'shared_item' => 40],
        );

        $this->assertSame(0, $job->tries);
        $this->assertSame(5, $job->maxExceptions);
    }

    public function test_a_skipped_older_command_is_finalized_instead_of_leaving_payment_pending(): void
    {
        $commandId = '0190f26e-7c87-7def-8e46-444444444444';
        $bus = Mockery::mock(CustomerCommandBus::class);
        $bus->shouldReceive('expectedSequence')->once()->with(20)->andReturn(3);
        $bus->shouldReceive('finish')->once()->with(
            $commandId,
            20,
            2,
            'superseded',
            ['message' => 'A newer customer command has already been processed.'],
        );

        $job = new ProcessCustomerCommand(
            $commandId,
            2,
            10,
            20,
            'order.share',
            ['order_id' => 30, 'unshared_item' => 40],
        );

        $job->handle($bus, Mockery::mock(CartController::class));
    }
}
