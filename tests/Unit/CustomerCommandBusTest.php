<?php

namespace Tests\Unit;

use App\Services\CustomerCommandBus;
use Mockery;
use Tests\TestCase;

class CustomerCommandBusTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_wait_for_session_proceeds_when_no_worker_is_draining_the_queue(): void
    {
        // With no worker alive, the pending counter can never be cleared, so the
        // barrier must not block order confirmation on it — it returns true
        // (proceed) without consulting the pending counter at all.
        $bus = Mockery::mock(CustomerCommandBus::class)->makePartial();
        $bus->shouldReceive('workerAlive')->once()->andReturnFalse();

        $this->assertTrue($bus->waitForSession(4242));
    }
}
