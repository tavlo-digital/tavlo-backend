<?php

namespace App\Jobs;

use App\Events\CustomerRealtimeNotification;
use App\Models\TableScanSession;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeliverCustomerRealtime implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $deliveryId,
        public readonly string $type,
        public readonly array $payload,
    ) {
        $this->onQueue((string) config('services.realtime.queue', 'realtime'));
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [1, 5, 15];
    }

    public function handle(): void
    {
        $customerIds = $this->customerIds();
        if ($customerIds->isEmpty()) {
            return;
        }

        CustomerRealtimeNotification::dispatch(
            $this->deliveryId,
            $customerIds->all(),
            (string) $this->payload['event'],
            (string) $this->payload['message'],
            (array) ($this->payload['metadata'] ?? []),
            (string) ($this->payload['created_at'] ?? now()->toISOString()),
        );
    }

    /** @return Collection<int, int> */
    private function customerIds(): Collection
    {
        if ($this->type === 'customers') {
            return collect($this->payload['customer_ids'] ?? [])
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
        }

        if ($this->type !== 'table') {
            return collect();
        }

        return TableScanSession::query()
            ->where('restaurant_table_id', $this->payload['restaurant_table_id'])
            ->where('status', 'active')
            ->whereNotNull('customer_id')
            ->pluck('customer_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Customer realtime delivery job failed.', [
            'delivery_id' => $this->deliveryId,
            'delivery_type' => $this->type,
            'exception' => $exception,
        ]);
    }
}
