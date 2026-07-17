<?php

namespace App\Jobs;

use App\Events\OperationalRealtimeNotification;
use App\Models\Notification;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeliverOperationalRealtime implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public int $uniqueFor = 300;

    public function __construct(
        public readonly string $deliveryId,
    ) {
        $this->onConnection((string) config('services.realtime.vendor_connection', 'redis'));
        $this->onQueue((string) config('services.realtime.vendor_queue', 'vendor-realtime'));
    }

    public function uniqueId(): string
    {
        return $this->deliveryId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [1, 5, 15];
    }

    public function handle(): void
    {
        $notifications = Notification::query()
            ->where('delivery_key', 'like', "{$this->deliveryId}:%")
            ->whereNull('customer_id')
            ->whereNull('admin_id')
            ->where(function ($query): void {
                $query->whereNotNull('waiter_id')
                    ->orWhereNotNull('kitchen_id')
                    ->orWhere(function ($vendorQuery): void {
                        $vendorQuery->whereNotNull('vendor_id')
                            ->whereNull('waiter_id')
                            ->whereNull('kitchen_id');
                    });
            })
            ->orderBy('id')
            ->get();

        foreach ($notifications as $notification) {
            [$role, $recipientId] = $this->recipient($notification);
            $metadata = $notification->metadata ?? [];

            OperationalRealtimeNotification::dispatch(
                $role,
                $recipientId,
                [
                    'id' => $notification->id,
                    'user_id' => $recipientId,
                    'event' => $notification->event,
                    'message' => $notification->message,
                    'read' => (bool) $notification->read,
                    'is_silent' => (bool) $notification->is_silent,
                    'user_role' => $role,
                    'vendor_id' => (int) $notification->vendor_id,
                    'metadata' => [
                        ...$metadata,
                        'event_id' => (string) ($metadata['event_id'] ?? $this->deliveryId),
                    ],
                    'created_at' => $notification->created_at?->toISOString(),
                    'updated_at' => $notification->updated_at?->toISOString(),
                ],
            );
        }
    }

    /** @return array{string, int} */
    private function recipient(Notification $notification): array
    {
        if ($notification->waiter_id !== null) {
            return ['waiter', (int) $notification->waiter_id];
        }

        if ($notification->kitchen_id !== null) {
            return ['kitchen', (int) $notification->kitchen_id];
        }

        return ['vendor', (int) $notification->vendor_id];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Operational realtime delivery job failed.', [
            'delivery_id' => $this->deliveryId,
            'exception' => $exception,
        ]);
    }
}
