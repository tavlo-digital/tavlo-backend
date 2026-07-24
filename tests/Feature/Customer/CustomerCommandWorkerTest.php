<?php

namespace Tests\Feature\Customer;

use App\Services\CustomerCommandBus;
use Illuminate\Support\Facades\Redis;
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
}
