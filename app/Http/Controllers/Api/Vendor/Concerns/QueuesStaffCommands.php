<?php

namespace App\Http\Controllers\Api\Vendor\Concerns;

use App\Exceptions\StaffCommandConflictException;
use App\Models\TeamMember;
use App\Services\StaffCommandBus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

trait QueuesStaffCommands
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, string>  $resources
     */
    protected function queuedStaffCommand(
        Request $request,
        StaffCommandBus $commands,
        string $operation,
        array $payload,
        array $resources,
    ): ?JsonResponse {
        $actor = $request->user();
        if (! $actor instanceof TeamMember || $request->attributes->getBoolean('staff_command_sync')) {
            return null;
        }

        if (! in_array($actor->role, ['waiter', 'kitchen'], true)) {
            abort(403, 'This staff role cannot dispatch operational commands.');
        }

        if (! $commands->enabled()) {
            // Explicit emergency rollback flag: retain the legacy synchronous
            // path. Once enabled, enqueue errors always fail closed below.
            return null;
        }

        $idempotencyKey = Validator::make([
            'idempotency_key' => $request->header('Idempotency-Key'),
        ], [
            'idempotency_key' => ['required', 'uuid'],
        ])->validate()['idempotency_key'];

        try {
            $status = $commands->dispatch(
                $actor,
                strtolower($idempotencyKey),
                $operation,
                $payload,
                $resources,
                $request->header('Accept-Language'),
            );
        } catch (StaffCommandConflictException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => 'idempotency_key_reused',
                'command_id' => $exception->commandId,
                'status_url' => $exception->commandId !== ''
                    ? "/api/vendor/commands/{$exception->commandId}"
                    : null,
            ], 409);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Staff command processing is temporarily unavailable.',
                'code' => 'staff_commands_unavailable',
            ], 503);
        }

        return response()->json($this->staffCommandAcceptedPayload($status), 202);
    }

    /** @param array<string, mixed> $status */
    protected function staffCommandAcceptedPayload(array $status): array
    {
        $commandId = (string) $status['command_id'];

        $payload = [
            'command_id' => $commandId,
            'idempotency_key' => $status['idempotency_key'] ?? null,
            'operation' => $status['operation'] ?? null,
            'status' => $status['status'] ?? 'accepted',
            'status_url' => "/api/vendor/commands/{$commandId}",
        ];

        foreach (['http_status', 'response', 'error', 'processed_at'] as $terminalField) {
            if (array_key_exists($terminalField, $status)) {
                $payload[$terminalField] = $status[$terminalField];
            }
        }

        return $payload;
    }
}
