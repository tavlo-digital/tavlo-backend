<?php

namespace App\Jobs;

use App\Http\Controllers\Api\Vendor\NotificationController;
use App\Http\Controllers\Api\Vendor\OrderController;
use App\Http\Controllers\Api\Vendor\StaffOrderController;
use App\Http\Controllers\Api\Vendor\TableController;
use App\Models\StaffCommand;
use App\Models\TeamMember;
use App\Services\NotificationService;
use App\Services\StaffCommandBus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ProcessStaffCommand implements ShouldQueue
{
    use Queueable;

    /**
     * Sequence-barrier and overlap releases are normal during a rapid burst and
     * must not consume a small attempt budget. The time-based retry deadline
     * below remains the terminal safety bound.
     */
    public int $tries = 0;

    public int $timeout = 90;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, int>  $resourceSequences
     */
    public function __construct(
        public readonly string $commandId,
        public readonly string $idempotencyKey,
        public readonly int $teamMemberId,
        public readonly int $vendorId,
        public readonly string $actorRole,
        public readonly string $operation,
        public readonly array $payload,
        public readonly array $resourceSequences,
        public readonly ?string $locale = null,
    ) {}

    public function middleware(): array
    {
        $lockSeconds = max(30, (int) config('services.staff_commands.lock_seconds', 120));

        return collect(array_keys($this->resourceSequences))
            ->sort()
            ->map(fn (string $resource) => (new WithoutOverlapping(
                'staff-command-resource:'.hash('sha256', $resource)
            ))->releaseAfter(1)->expireAfter($lockSeconds))
            ->values()
            ->all();
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [1, 2, 5, 10];
    }

    public function retryUntil(): \DateTimeInterface
    {
        $statusTtl = max(300, (int) config('services.staff_commands.status_ttl', 3600));

        return now()->addSeconds(max(60, $statusTtl - 30));
    }

    public function handle(
        StaffCommandBus $bus,
        OrderController $orders,
        TableController $tables,
        StaffOrderController $staffOrders,
        NotificationController $notifications,
    ): void {
        $existing = StaffCommand::query()->where('command_id', $this->commandId)->first();
        if ($existing && in_array($existing->status, ['completed', 'failed'], true)) {
            $bus->finish(
                $this->commandId,
                $this->resourceSequences,
                $existing->status,
                $this->terminalResult($existing->http_status ?? 500, (array) $existing->response, $existing->error),
            );

            return;
        }

        $sequenceState = $bus->sequenceState($this->resourceSequences);
        if ($sequenceState === 'waiting') {
            $this->release(1);

            return;
        }
        if ($sequenceState === 'complete') {
            return;
        }

        $actor = TeamMember::query()->find($this->teamMemberId);
        $authorizationError = $this->authorizationError($actor);
        if ($authorizationError !== null) {
            $this->completeWithoutMutation($bus, 403, ['message' => $authorizationError]);

            return;
        }

        $bus->markProcessing($this->commandId);

        $responsePayload = [];
        $responseStatus = 500;
        $alreadyProcessed = false;

        DB::transaction(function () use (
            $actor,
            $orders,
            $tables,
            $staffOrders,
            $notifications,
            &$responsePayload,
            &$responseStatus,
            &$alreadyProcessed,
        ): void {
            $command = StaffCommand::query()->firstOrCreate(
                ['command_id' => $this->commandId],
                [
                    'idempotency_key' => $this->idempotencyKey,
                    'team_member_id' => $this->teamMemberId,
                    'vendor_id' => $this->vendorId,
                    'actor_role' => $this->actorRole,
                    'operation' => $this->operation,
                    'status' => 'processing',
                    'payload' => $this->payload,
                    'resource_sequences' => $this->resourceSequences,
                ],
            );

            if (in_array($command->status, ['completed', 'failed'], true)) {
                $alreadyProcessed = true;
                $responsePayload = (array) $command->response;
                $responseStatus = $command->http_status ?? 500;

                return;
            }

            $request = $this->requestFor($actor);
            try {
                $response = $this->execute($orders, $tables, $staffOrders, $notifications, $request);
            } catch (ValidationException|ModelNotFoundException|AuthorizationException|AuthenticationException|HttpExceptionInterface $exception) {
                $response = $this->renderExpectedException($request, $exception);
            }

            $responseStatus = $response->getStatusCode();
            $decoded = json_decode((string) $response->getContent(), true);
            $responsePayload = is_array($decoded) ? $decoded : ['data' => $decoded];
            $status = $responseStatus < 400 ? 'completed' : 'failed';

            $command->update([
                'status' => $status,
                'http_status' => $responseStatus,
                'response' => $responsePayload,
                'error' => $status === 'failed'
                    ? (string) ($responsePayload['message'] ?? 'Staff command failed.')
                    : null,
                'processed_at' => now(),
            ]);
        });

        $status = $responseStatus < 400 ? 'completed' : 'failed';
        $error = $status === 'failed'
            ? (string) ($responsePayload['message'] ?? 'Staff command failed.')
            : null;
        $terminal = $bus->finish(
            $this->commandId,
            $this->resourceSequences,
            $status,
            $this->terminalResult($responseStatus, $responsePayload, $error),
        );

        if (! $alreadyProcessed) {
            $this->notifyTerminal($status, $terminal);
        }
    }

    private function requestFor(TeamMember $actor): Request
    {
        $request = Request::create('/api/vendor/staff-command', $this->httpMethod(), $this->payload);
        $request->setUserResolver(fn () => $actor);
        $request->attributes->set('staff_command_sync', true);
        $request->attributes->set('staff_command_id', $this->commandId);
        $request->headers->set('Accept', 'application/json');
        if ($this->locale) {
            $request->headers->set('Accept-Language', $this->locale);
        }

        return $request;
    }

    private function execute(
        OrderController $orders,
        TableController $tables,
        StaffOrderController $staffOrders,
        NotificationController $notifications,
        Request $request,
    ): JsonResponse {
        return match ($this->operation) {
            'order.confirm' => $orders->confirm($request, (string) $this->payload['order_id']),
            'order.confirm_cash' => $orders->confirmCashPayment($request, (string) $this->payload['order_id']),
            'order.ready' => $orders->markReady($request, (string) $this->payload['order_id']),
            'order.item_status' => $orders->updateItemStatus(
                $request,
                (string) $this->payload['order_id'],
                (string) $this->payload['cart_item_id'],
            ),
            'order.items_serve_ready' => $orders->serveReadyItems(
                $request,
                (string) $this->payload['order_id'],
            ),
            'order.served' => $orders->markServed($request, (string) $this->payload['order_id']),
            'table.close' => $tables->closeSession(
                $request,
                (string) $this->payload['vendor_id'],
                (string) $this->payload['table_id'],
            ),
            'table.dismiss_call' => $tables->dismissCall(
                $request,
                (string) $this->payload['vendor_id'],
                (string) $this->payload['table_id'],
            ),
            'table.transfer' => $tables->transfer(
                $request,
                (string) $this->payload['vendor_id'],
                (string) $this->payload['table_id'],
            ),
            'table.staff_order' => $staffOrders->store(
                $request,
                (string) $this->payload['vendor_id'],
                (string) $this->payload['table_id'],
            ),
            'table.create_session' => $tables->createSession(
                $request,
                (string) $this->payload['vendor_id'],
                (string) $this->payload['table_id'],
            ),
            'notification.read' => $notifications->markRead(
                $request,
                (int) $this->payload['notification_id'],
            ),
            'notification.read_all' => $notifications->markAllRead($request),
            default => throw new RuntimeException("Unsupported staff command [{$this->operation}]."),
        };
    }

    private function httpMethod(): string
    {
        return str_starts_with($this->operation, 'order.') || $this->operation === 'notification.read'
            ? 'PATCH'
            : 'POST';
    }

    private function authorizationError(?TeamMember $actor): ?string
    {
        if (! $actor) {
            return 'The staff actor no longer exists.';
        }
        if ($actor->status !== 'active') {
            return 'The staff actor is no longer active.';
        }
        if ((int) $actor->vendor_id !== $this->vendorId || $actor->role !== $this->actorRole) {
            return 'The staff actor authorization has changed.';
        }

        $allowed = match ($actor->role) {
            'kitchen' => in_array($this->operation, [
                'order.ready',
                'order.item_status',
                'notification.read',
                'notification.read_all',
            ], true) && ($this->operation !== 'order.item_status'
                || in_array($this->payload['status'] ?? null, ['new', 'preparing', 'ready'], true)),
            'waiter' => in_array($this->operation, [
                'order.confirm',
                'order.confirm_cash',
                'order.item_status',
                'order.items_serve_ready',
                'order.served',
                'table.close',
                'table.dismiss_call',
                'table.transfer',
                'table.staff_order',
                'table.create_session',
                'notification.read',
                'notification.read_all',
            ], true) && ($this->operation !== 'order.item_status' || ($this->payload['status'] ?? null) === 'served'),
            default => false,
        };

        return $allowed ? null : 'The staff role is not authorized for this command.';
    }

    private function renderExpectedException(Request $request, Throwable $exception): JsonResponse
    {
        $response = app(ExceptionHandler::class)->render($request, $exception);
        $decoded = json_decode((string) $response->getContent(), true);

        return response()->json(
            is_array($decoded) ? $decoded : ['message' => 'Staff command failed.'],
            $response->getStatusCode(),
        );
    }

    /** @param array<string, mixed> $response */
    private function terminalResult(int $httpStatus, array $response, ?string $error): array
    {
        return [
            'http_status' => $httpStatus,
            'response' => $response,
            'error' => $error,
            'processed_at' => now()->toISOString(),
        ];
    }

    /** @param array<string, mixed> $response */
    private function completeWithoutMutation(StaffCommandBus $bus, int $httpStatus, array $response): void
    {
        $error = (string) ($response['message'] ?? 'Staff command failed.');
        StaffCommand::query()->updateOrCreate(
            ['command_id' => $this->commandId],
            [
                'idempotency_key' => $this->idempotencyKey,
                'team_member_id' => $this->teamMemberId,
                'vendor_id' => $this->vendorId,
                'actor_role' => $this->actorRole,
                'operation' => $this->operation,
                'status' => 'failed',
                'payload' => $this->payload,
                'resource_sequences' => $this->resourceSequences,
                'http_status' => $httpStatus,
                'response' => $response,
                'error' => $error,
                'processed_at' => now(),
            ],
        );

        $terminal = $bus->finish(
            $this->commandId,
            $this->resourceSequences,
            'failed',
            $this->terminalResult($httpStatus, $response, $error),
        );
        $this->notifyTerminal('failed', $terminal);
    }

    /** @param array<string, mixed> $terminal */
    private function notifyTerminal(string $status, array $terminal): void
    {
        NotificationService::notifyOperationalActor(
            $this->vendorId,
            $this->actorRole,
            $this->teamMemberId,
            $status === 'completed' ? 'staff_command_completed' : 'staff_command_failed',
            $status === 'completed' ? 'Staff command completed.' : 'Staff command failed.',
            [
                'command_id' => $this->commandId,
                'command_status' => $status,
                'operation' => $this->operation,
                'resources' => array_keys($this->resourceSequences),
                'http_status' => $terminal['http_status'] ?? null,
                'response' => $terminal['response'] ?? null,
                'error' => $terminal['error'] ?? null,
            ],
            true,
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Staff command failed.', [
            'command_id' => $this->commandId,
            'operation' => $this->operation,
            'exception' => $exception,
        ]);

        try {
            $response = ['message' => 'The staff command could not be processed.'];
            $this->completeWithoutMutation(app(StaffCommandBus::class), 500, $response);
        } catch (Throwable $terminalException) {
            Log::error('Staff command terminal state could not be recorded.', [
                'command_id' => $this->commandId,
                'exception' => $terminalException,
            ]);
        }
    }
}
