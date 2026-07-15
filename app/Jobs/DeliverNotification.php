<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeliverNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $deliveryId,
        public readonly string $type,
        public readonly array $payload,
    ) {
        $this->onQueue((string) config('services.notifications.queue', 'notifications'));
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [1, 5, 15];
    }

    public function handle(): void
    {
        NotificationService::deliver($this->deliveryId, $this->type, $this->payload);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Notification delivery job failed.', [
            'delivery_id' => $this->deliveryId,
            'delivery_type' => $this->type,
            'exception' => $exception,
        ]);
    }
}
