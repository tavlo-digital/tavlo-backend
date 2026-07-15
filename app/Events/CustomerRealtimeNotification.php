<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CustomerRealtimeNotification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int>  $customerIds
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $deliveryId,
        public readonly array $customerIds,
        public readonly string $event,
        public readonly string $message,
        public readonly array $metadata,
        public readonly string $createdAt,
    ) {}

    /** @return array<int, PrivateChannel> */
    public function broadcastOn(): array
    {
        return collect($this->customerIds)
            ->map(fn (int $customerId) => new PrivateChannel("customer.{$customerId}"))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->deliveryId,
            'user_id' => null,
            'event' => $this->event,
            'message' => $this->message,
            'read' => false,
            'user_role' => 'customer',
            'metadata' => [
                ...$this->metadata,
                'event_id' => $this->deliveryId,
            ],
            'created_at' => $this->createdAt,
            'updated_at' => $this->createdAt,
        ];
    }
}
