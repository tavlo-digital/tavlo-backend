<?php

namespace App\Services;

use App\Exceptions\StaffCommandConflictException;
use App\Jobs\ProcessStaffCommand;
use App\Models\StaffCommand;
use App\Models\TeamMember;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StaffCommandBus
{
    public function enabled(): bool
    {
        return (bool) config('services.staff_commands.enabled', false);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $resources
     * @return array<string, mixed>
     */
    public function dispatch(
        TeamMember $actor,
        string $idempotencyKey,
        string $operation,
        array $payload,
        array $resources,
        ?string $locale = null,
    ): array {
        if (! $this->enabled()) {
            throw new RuntimeException('Staff command processing is unavailable.');
        }

        $resources = collect($resources)
            ->filter(fn ($resource) => is_string($resource) && $resource !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        if ($resources === []) {
            throw new RuntimeException('A staff command must identify at least one resource.');
        }

        $commandId = (string) Str::uuid7();
        $ttl = $this->ttl();
        $fingerprint = hash('sha256', json_encode($this->canonicalize([
            'vendor_id' => (int) $actor->vendor_id,
            'operation' => $operation,
            'payload' => $payload,
            'resources' => $resources,
        ]), JSON_THROW_ON_ERROR));

        $identity = [
            'command_id' => $commandId,
            'fingerprint' => $fingerprint,
        ];
        $initialStatus = [
            'command_id' => $commandId,
            'idempotency_key' => $idempotencyKey,
            'team_member_id' => (int) $actor->id,
            'vendor_id' => (int) $actor->vendor_id,
            'actor_role' => (string) $actor->role,
            'operation' => $operation,
            'status' => 'dispatching',
            'resources' => $resources,
        ];

        $keys = [
            $this->idempotencyKey((int) $actor->id, $idempotencyKey),
            $this->statusKey($commandId),
            ...array_map(fn (string $resource) => $this->sequenceKey($resource), $resources),
        ];
        $dispatchScript = <<<'LUA'
local existing = redis.call('GET', KEYS[1])
if existing then
    local identity = cjson.decode(existing)
    if identity['fingerprint'] ~= ARGV[1] then
        return cjson.encode({result = 'conflict', command_id = identity['command_id']})
    end
    return cjson.encode({result = 'duplicate', command_id = identity['command_id']})
end

local status = cjson.decode(ARGV[3])
local resources = cjson.decode(ARGV[5])
status['resource_sequences'] = {}
for index = 3, #KEYS do
    local sequence = redis.call('INCR', KEYS[index])
    redis.call('EXPIRE', KEYS[index], tonumber(ARGV[4]))
    status['resource_sequences'][resources[index - 2]] = sequence
end

redis.call('SETEX', KEYS[1], tonumber(ARGV[4]), ARGV[2])
redis.call('SETEX', KEYS[2], tonumber(ARGV[4]), cjson.encode(status))
return cjson.encode({result = 'new', status = status})
LUA;
        $scriptArguments = [
            count($keys),
            ...$keys,
            $fingerprint,
            json_encode($identity, JSON_THROW_ON_ERROR),
            json_encode($initialStatus, JSON_THROW_ON_ERROR),
            (string) $ttl,
            json_encode($resources, JSON_THROW_ON_ERROR),
        ];
        $result = $this->decodeScriptResult(Redis::eval($dispatchScript, ...$scriptArguments));

        if (($result['result'] ?? null) === 'conflict') {
            throw new StaffCommandConflictException((string) ($result['command_id'] ?? ''));
        }

        if (($result['result'] ?? null) === 'duplicate') {
            return $this->waitForDispatchedStatus((string) $result['command_id']);
        }

        $status = (array) ($result['status'] ?? []);
        $resourceSequences = (array) ($status['resource_sequences'] ?? []);

        try {
            ProcessStaffCommand::dispatch(
                $commandId,
                $idempotencyKey,
                (int) $actor->id,
                (int) $actor->vendor_id,
                (string) $actor->role,
                $operation,
                $payload,
                $resourceSequences,
                $locale,
            )
                ->onConnection((string) config('services.staff_commands.connection', 'redis'))
                ->onQueue((string) config('services.staff_commands.queue', 'staffcommands'));
        } catch (Throwable $exception) {
            $this->finish($commandId, $resourceSequences, 'failed', [
                'http_status' => 503,
                'response' => ['message' => 'Staff command could not be enqueued.'],
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }

        $this->markAccepted($commandId);

        return $this->status($commandId) ?? [
            ...$status,
            'status' => 'accepted',
        ];
    }

    /** @return array<string, mixed>|null */
    public function status(string $commandId): ?array
    {
        $value = Redis::get($this->statusKey($commandId));

        return is_string($value) ? json_decode($value, true) : null;
    }

    /** @return array<string, mixed>|null */
    public function statusForActor(TeamMember $actor, string $commandId): ?array
    {
        try {
            $status = $this->status($commandId);
        } catch (Throwable $exception) {
            report($exception);
            $status = null;
        }
        if ($status
            && (int) ($status['team_member_id'] ?? 0) === (int) $actor->id
            && (int) ($status['vendor_id'] ?? 0) === (int) $actor->vendor_id) {
            return $status;
        }

        $command = StaffCommand::query()
            ->where('command_id', $commandId)
            ->where('team_member_id', $actor->id)
            ->where('vendor_id', $actor->vendor_id)
            ->first();

        return $command ? $this->databaseStatus($command) : null;
    }

    public function markProcessing(string $commandId): void
    {
        Redis::eval(
            <<<'LUA'
local value = redis.call('GET', KEYS[1])
if not value then return 0 end
local status = cjson.decode(value)
if status['status'] == 'accepted' or status['status'] == 'dispatching' then
    status['status'] = 'processing'
    redis.call('SETEX', KEYS[1], tonumber(ARGV[1]), cjson.encode(status))
end
return 1
LUA,
            1,
            $this->statusKey($commandId),
            (string) $this->ttl(),
        );
    }

    /**
     * @param  array<string, int>  $resourceSequences
     * @return string ready, waiting, or complete
     */
    public function sequenceState(array $resourceSequences): string
    {
        $completed = [];
        foreach ($resourceSequences as $resource => $sequence) {
            $completed[(string) $resource] = (int) (Redis::get($this->completedKey((string) $resource)) ?? 0);
        }

        if (collect($resourceSequences)->every(
            fn ($sequence, $resource) => (int) $sequence <= ($completed[(string) $resource] ?? 0)
        )) {
            return 'complete';
        }

        if (collect($resourceSequences)->every(
            fn ($sequence, $resource) => (int) $sequence === (($completed[(string) $resource] ?? 0) + 1)
        )) {
            return 'ready';
        }

        return 'waiting';
    }

    /**
     * @param  array<string, int>  $resourceSequences
     * @param  array<string, mixed>  $result
     */
    public function finish(
        string $commandId,
        array $resourceSequences,
        string $status,
        array $result = [],
    ): array {
        $current = $this->status($commandId) ?? [];
        $terminal = [
            ...$current,
            'command_id' => $commandId,
            'status' => $status,
            ...$result,
        ];

        $resources = array_keys($resourceSequences);
        sort($resources);
        $keys = [$this->statusKey($commandId)];
        foreach ($resources as $resource) {
            $keys[] = $this->completedKey($resource);
            $keys[] = $this->finishedSetKey($resource);
        }

        $finishScript = <<<'LUA'
redis.call('SETEX', KEYS[1], tonumber(ARGV[1]), ARGV[2])
local sequences = cjson.decode(ARGV[3])
for index = 2, #KEYS, 2 do
    local sequence = tonumber(sequences[((index - 2) / 2) + 1])
    redis.call('ZADD', KEYS[index + 1], sequence, tostring(sequence))
    local completed = tonumber(redis.call('GET', KEYS[index]) or '0')
    while redis.call('ZSCORE', KEYS[index + 1], tostring(completed + 1)) do
        redis.call('ZREM', KEYS[index + 1], tostring(completed + 1))
        completed = completed + 1
    end
    redis.call('SETEX', KEYS[index], tonumber(ARGV[1]), tostring(completed))
    redis.call('EXPIRE', KEYS[index + 1], tonumber(ARGV[1]))
end
return 1
LUA;
        $scriptArguments = [
            count($keys),
            ...$keys,
            (string) $this->ttl(),
            json_encode($terminal, JSON_THROW_ON_ERROR),
            json_encode(array_map(fn (string $resource) => (int) $resourceSequences[$resource], $resources), JSON_THROW_ON_ERROR),
        ];
        Redis::eval($finishScript, ...$scriptArguments);

        return $terminal;
    }

    /** @return array<string, mixed> */
    public function databaseStatus(StaffCommand $command): array
    {
        return [
            'command_id' => $command->command_id,
            'idempotency_key' => $command->idempotency_key,
            'team_member_id' => (int) $command->team_member_id,
            'vendor_id' => (int) $command->vendor_id,
            'actor_role' => $command->actor_role,
            'operation' => $command->operation,
            'status' => $command->status,
            'resources' => array_keys((array) $command->resource_sequences),
            'resource_sequences' => $command->resource_sequences,
            'http_status' => $command->http_status,
            'response' => $command->response,
            'error' => $command->error,
            'processed_at' => $command->processed_at?->toISOString(),
        ];
    }

    private function markAccepted(string $commandId): void
    {
        Redis::eval(
            <<<'LUA'
local value = redis.call('GET', KEYS[1])
if not value then return 0 end
local status = cjson.decode(value)
if status['status'] == 'dispatching' then
    status['status'] = 'accepted'
    redis.call('SETEX', KEYS[1], tonumber(ARGV[1]), cjson.encode(status))
end
return 1
LUA,
            1,
            $this->statusKey($commandId),
            (string) $this->ttl(),
        );
    }

    /** @return array<string, mixed> */
    private function waitForDispatchedStatus(string $commandId): array
    {
        $deadline = microtime(true) + 0.75;
        do {
            $status = $this->status($commandId);
            if ($status && ($status['status'] ?? null) !== 'dispatching') {
                return $status;
            }
            usleep(20_000);
        } while (microtime(true) < $deadline);

        throw new RuntimeException('The original staff command is still being enqueued.');
    }

    /** @return array<string, mixed> */
    private function decodeScriptResult(mixed $value): array
    {
        if (! is_string($value)) {
            throw new RuntimeException('Redis returned an invalid staff command result.');
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Redis returned malformed staff command state.');
        }

        return $decoded;
    }

    private function ttl(): int
    {
        return max(300, (int) config('services.staff_commands.status_ttl', 3600));
    }

    private function idempotencyKey(int $actorId, string $idempotencyKey): string
    {
        return "staff-command:idempotency:{$actorId}:{$idempotencyKey}";
    }

    private function statusKey(string $commandId): string
    {
        return "staff-command:status:{$commandId}";
    }

    private function sequenceKey(string $resource): string
    {
        return 'staff-command:sequence:'.hash('sha256', $resource);
    }

    private function completedKey(string $resource): string
    {
        return 'staff-command:completed:'.hash('sha256', $resource);
    }

    private function finishedSetKey(string $resource): string
    {
        return 'staff-command:finished:'.hash('sha256', $resource);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn ($item) => $this->canonicalize($item), $value);
    }
}
