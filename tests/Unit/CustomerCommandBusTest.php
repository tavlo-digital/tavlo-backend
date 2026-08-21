<?php

namespace Tests\Unit;

use App\Services\CustomerCommandBus;
use Illuminate\Support\Facades\Redis;
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

    public function test_finishing_an_older_command_clears_pending_without_moving_sequence_backwards(): void
    {
        config()->set('services.customer_commands.status_ttl', 3600);

        Redis::shouldReceive('get')
            ->once()
            ->with('customer-command:status:command-13')
            ->andReturn(json_encode([
                'command_id' => 'command-13',
                'customer_id' => 29,
                'table_scan_session_id' => 53,
                'sequence' => 13,
                'operation' => 'order.share',
                'status' => 'accepted',
            ], JSON_THROW_ON_ERROR));
        Redis::shouldReceive('setex')
            ->once()
            ->with(
                'customer-command:status:command-13',
                3600,
                Mockery::on(function (string $value): bool {
                    $status = json_decode($value, true, flags: JSON_THROW_ON_ERROR);

                    return $status['status'] === 'superseded'
                        && $status['customer_id'] === 29
                        && $status['table_scan_session_id'] === 53
                        && $status['operation'] === 'order.share';
                }),
            );
        Redis::shouldReceive('get')
            ->once()
            ->with('customer-command:completed:53')
            ->andReturn('17');
        Redis::shouldReceive('setex')
            ->once()
            ->with('customer-command:completed:53', 3600, '17');
        Redis::shouldReceive('decr')
            ->once()
            ->with('customer-command:pending:53')
            ->andReturn(0);
        Redis::shouldReceive('del')
            ->once()
            ->with('customer-command:pending:53');

        app(CustomerCommandBus::class)->finish('command-13', 53, 13, 'superseded');
        $this->addToAssertionCount(1);
    }
}
