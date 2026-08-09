<?php

namespace App\Jobs;

use App\Http\Controllers\Api\Customer\CartController;
use App\Models\Customer;
use App\Models\CustomerCommand;
use App\Services\CustomerApiCache;
use App\Services\CustomerCommandBus;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessCustomerCommand implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    /** @param array<string, mixed> $payload */
    public function __construct(
        public readonly string $commandId,
        public readonly int $sequence,
        public readonly int $customerId,
        public readonly int $sessionId,
        public readonly string $operation,
        public readonly array $payload,
        public readonly ?string $locale = null,
        public readonly ?string $orderMode = null,
    ) {}

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("customer-command-session:{$this->sessionId}"))
                ->releaseAfter(1)
                ->expireAfter(90),
        ];
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [1, 2, 5, 10];
    }

    public function handle(CustomerCommandBus $bus, CartController $cart): void
    {
        $expected = $bus->expectedSequence($this->sessionId);
        if ($this->sequence > $expected) {
            $this->release(1);

            return;
        }
        if ($this->sequence < $expected) {
            return;
        }

        $customer = Customer::find($this->customerId);
        if (! $customer) {
            throw new RuntimeException('Customer no longer exists.');
        }

        $responsePayload = [];
        $responseStatus = 500;

        DB::transaction(function () use ($cart, $customer, &$responsePayload, &$responseStatus): void {
            $command = CustomerCommand::query()->firstOrCreate(
                ['command_id' => $this->commandId],
                [
                    'customer_id' => $this->customerId,
                    'table_scan_session_id' => $this->sessionId,
                    'sequence' => $this->sequence,
                    'operation' => $this->operation,
                    'status' => 'processing',
                    'payload' => $this->payload,
                ],
            );

            if ($command->status === 'completed') {
                $responsePayload = (array) $command->response;
                $responseStatus = 200;

                return;
            }

            $request = Request::create('/', $this->httpMethod(), $this->payload);
            $request->setUserResolver(fn () => $customer);
            $request->attributes->set('customer_command_sync', true);
            $request->attributes->set('customer_command_id', $this->commandId);
            if ($this->locale) {
                $request->headers->set('Accept-Language', $this->locale);
            }
            if ($this->orderMode) {
                $request->headers->set('X-Order-Mode', $this->orderMode);
            }

            $response = $this->execute($cart, $request);
            $responseStatus = $response->getStatusCode();
            $decoded = json_decode((string) $response->getContent(), true);
            $responsePayload = is_array($decoded) ? $decoded : [];
            $storedResponse = $this->compactResponse($responsePayload);

            $command->update([
                'status' => $responseStatus < 400 ? 'completed' : 'failed',
                'response' => $storedResponse,
                'error' => $responseStatus < 400 ? null : ($responsePayload['message'] ?? 'Command failed.'),
                'processed_at' => now(),
            ]);
        });

        $status = $responseStatus < 400 ? 'completed' : 'failed';
        $bus->finish($this->commandId, $this->sessionId, $this->sequence, $status, [
            'http_status' => $responseStatus,
            'response' => $this->compactResponse($responsePayload),
        ]);

        app(CustomerApiCache::class)->invalidate();

        if ($status === 'failed') {
            NotificationService::notifyCustomers(
                collect([$this->customerId]),
                'customer_command_failed',
                (string) ($responsePayload['message'] ?? 'The requested change could not be saved.'),
                metadata: [
                    'command_id' => $this->commandId,
                    'command_status' => 'failed',
                    'operation' => $this->operation,
                    'error' => $responsePayload['message'] ?? 'Command failed.',
                ],
            );
        }
    }

    private function execute(CartController $cart, Request $request): JsonResponse
    {
        return match ($this->operation) {
            'cart.add' => $cart->addItem($request),
            'cart.update' => $cart->updateItem($request, (int) $this->payload['cart_item_id']),
            'cart.remove' => $cart->removeItem($request, (int) $this->payload['cart_item_id']),
            'order.share' => $cart->updateOrder($request, (int) $this->payload['order_id']),
            default => throw new RuntimeException("Unsupported customer command [{$this->operation}]."),
        };
    }

    private function httpMethod(): string
    {
        return match ($this->operation) {
            'cart.add' => 'POST',
            'cart.update' => 'PATCH',
            'cart.remove' => 'DELETE',
            'order.share' => 'PUT',
            default => 'POST',
        };
    }

    /** @param array<string, mixed> $response */
    private function compactResponse(array $response): array
    {
        return array_intersect_key($response, array_flip([
            'message',
            'id',
            'client_item_id',
            'state_patch',
            'order_snapshots',
            'removed_order_ids',
        ]));
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Customer command failed.', [
            'command_id' => $this->commandId,
            'operation' => $this->operation,
            'exception' => $exception,
        ]);

        app(CustomerCommandBus::class)->finish(
            $this->commandId,
            $this->sessionId,
            $this->sequence,
            'failed',
            ['message' => $exception->getMessage()],
        );
        app(CustomerApiCache::class)->invalidate();

        NotificationService::notifyCustomers(
            collect([$this->customerId]),
            'customer_command_failed',
            'The requested change could not be saved.',
            metadata: [
                'command_id' => $this->commandId,
                'command_status' => 'failed',
                'operation' => $this->operation,
            ],
        );
    }
}
