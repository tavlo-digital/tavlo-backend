<?php

namespace App\Services;

use App\Jobs\ProcessCustomerCommand;
use App\Models\Customer;
use App\Models\TableScanSession;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class CustomerCommandBus
{
    public function enabled(): bool
    {
        return (bool) config('services.customer_commands.enabled', false);
    }

    /**
     * Whether a queue worker is currently consuming the customer command queue.
     *
     * A running worker refreshes a heartbeat on every poll (see the queue event
     * listeners in AppServiceProvider). If the newest heartbeat is stale — or
     * absent — there is nothing draining the queue, so callers should process
     * the command synchronously rather than enqueue it into a dead queue where
     * it would be silently lost. Fails safe (returns false) if Redis is
     * unreachable, since we cannot enqueue reliably in that case either.
     */
    public function workerAlive(): bool
    {
        $maxAge = (int) config('services.customer_commands.worker_heartbeat_max_age', 15);

        // A non-positive max age disables the liveness guard entirely.
        if ($maxAge <= 0) {
            return true;
        }

        try {
            $last = Redis::get($this->heartbeatKey());
        } catch (Throwable $exception) {
            return false;
        }

        if (! is_numeric($last)) {
            return false;
        }

        return (time() - (int) $last) <= $maxAge;
    }

    /**
     * Record that a worker consuming the customer command queue is alive right
     * now. Called from queue lifecycle events; best-effort.
     */
    public function heartbeat(): void
    {
        $ttl = max(1, (int) config('services.customer_commands.worker_heartbeat_ttl', 30));

        try {
            Redis::setex($this->heartbeatKey(), $ttl, (string) time());
        } catch (Throwable $exception) {
            // Best-effort: a missed heartbeat only costs a synchronous fallback.
        }
    }

    /** @param array<string, mixed> $payload */
    public function dispatch(
        Customer $customer,
        TableScanSession $session,
        string $operation,
        array $payload,
        ?string $locale = null,
    ): array {
        $commandId = (string) Str::uuid7();
        $sequence = (int) Redis::incr($this->sequenceKey((int) $session->id));
        $ttl = (int) config('services.customer_commands.status_ttl', 3600);
        $status = [
            'command_id' => $commandId,
            'customer_id' => (int) $customer->id,
            'sequence' => $sequence,
            'operation' => $operation,
            'status' => 'accepted',
        ];

        Redis::setex($this->statusKey($commandId), $ttl, json_encode($status, JSON_THROW_ON_ERROR));
        Redis::expire($this->sequenceKey((int) $session->id), $ttl);
        Redis::incr($this->pendingKey((int) $session->id));
        Redis::expire($this->pendingKey((int) $session->id), $ttl);

        try {
            ProcessCustomerCommand::dispatch(
                $commandId,
                $sequence,
                (int) $customer->id,
                (int) $session->id,
                $operation,
                $payload,
                $locale,
            )
                ->onConnection((string) config('services.customer_commands.connection', 'redis'))
                ->onQueue((string) config('services.customer_commands.queue', 'customercommands'));
        } catch (Throwable $exception) {
            $this->finish($commandId, (int) $session->id, $sequence, 'enqueue_failed', [
                'message' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        return $status;
    }

    /** @return array<string, mixed>|null */
    public function status(string $commandId): ?array
    {
        $value = Redis::get($this->statusKey($commandId));

        return is_string($value) ? json_decode($value, true) : null;
    }

    /** @param array<string, mixed> $result */
    public function finish(
        string $commandId,
        int $sessionId,
        int $sequence,
        string $status,
        array $result = [],
    ): void {
        $ttl = (int) config('services.customer_commands.status_ttl', 3600);
        $current = $this->status($commandId);
        Redis::setex($this->statusKey($commandId), $ttl, json_encode([
            'command_id' => $commandId,
            'customer_id' => (int) ($current['customer_id'] ?? 0),
            'sequence' => $sequence,
            'status' => $status,
            ...$result,
        ], JSON_THROW_ON_ERROR));
        Redis::setex($this->completedKey($sessionId), $ttl, (string) $sequence);
        if ((int) Redis::decr($this->pendingKey($sessionId)) <= 0) {
            Redis::del($this->pendingKey($sessionId));
        }
    }

    public function waitForSession(int $sessionId): bool
    {
        // Only a live worker can drain the queue and clear the pending counter.
        // If none is draining it, cart writes were (or will be) applied
        // synchronously, and blocking on a possibly-stale pending counter would
        // wedge order confirmation for the whole status TTL. Proceed instead.
        if (! $this->workerAlive()) {
            return true;
        }

        $timeoutMs = max(0, (int) config('services.customer_commands.barrier_timeout_ms', 2000));
        $deadline = microtime(true) + ($timeoutMs / 1000);

        try {
            do {
                if ((int) (Redis::get($this->pendingKey($sessionId)) ?? 0) === 0) {
                    return true;
                }
                usleep(25_000);
            } while (microtime(true) < $deadline);
        } catch (Throwable $exception) {
            report($exception);

            return true;
        }

        return false;
    }

    public function expectedSequence(int $sessionId): int
    {
        return ((int) (Redis::get($this->completedKey($sessionId)) ?? 0)) + 1;
    }

    private function heartbeatKey(): string
    {
        return 'customer-command:worker:heartbeat';
    }

    private function statusKey(string $commandId): string
    {
        return "customer-command:status:{$commandId}";
    }

    private function sequenceKey(int $sessionId): string
    {
        return "customer-command:sequence:{$sessionId}";
    }

    private function completedKey(int $sessionId): string
    {
        return "customer-command:completed:{$sessionId}";
    }

    private function pendingKey(int $sessionId): string
    {
        return "customer-command:pending:{$sessionId}";
    }
}
