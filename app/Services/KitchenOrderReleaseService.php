<?php

namespace App\Services;

use App\Models\Order;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class KitchenOrderReleaseService
{
    public const PREPARATION_WINDOW_MINUTES = 20;

    public function __construct(
        private readonly OrderSessionService $orderSessions,
    ) {}

    public function notifyPaidOffPremiseOrder(
        Order $order,
        string $sourceActorType,
        ?int $sourceActorId,
    ): void {
        $order->loadMissing('tableScanSession.restaurantTable');
        if (! $order->tableScanSession || ! $this->orderSessions->isOffPremise($order->tableScanSession)) {
            return;
        }

        NotificationService::notifyOperations(
            (int) $order->vendor_id,
            'order_confirmed',
            'A paid off-premise order was confirmed.',
            [NotificationService::VENDOR, NotificationService::WAITER],
            $this->notificationMetadata($order, $sourceActorType, $sourceActorId),
        );

        if (! $this->isDue($order)) {
            NotificationService::notifyOperations(
                (int) $order->vendor_id,
                'order_scheduled',
                'A scheduled pickup was added to the pickup queue.',
                [NotificationService::KITCHEN],
                $this->notificationMetadata($order, $sourceActorType, $sourceActorId),
                true,
            );
        }

        $this->releaseIfDue($order, $sourceActorType, $sourceActorId);
    }

    public function releaseIfDue(
        Order $order,
        string $sourceActorType = 'system',
        ?int $sourceActorId = null,
        ?CarbonInterface $at = null,
    ): bool {
        $at ??= now();
        $releasedOrder = DB::transaction(function () use ($order, $at): ?Order {
            $locked = Order::query()
                ->with(['customer:id,first_name,last_name,email,phone', 'vendor:id,country', 'tableScanSession.restaurantTable:id,number,name'])
                ->lockForUpdate()
                ->find($order->id);

            if (! $locked || $locked->kitchen_released_at || ! $this->isDue($locked, $at)) {
                return null;
            }

            $locked->forceFill(['kitchen_released_at' => $at])->save();

            return $locked->fresh([
                'customer:id,first_name,last_name,email,phone',
                'vendor:id,country',
                'tableScanSession.restaurantTable:id,number,name',
            ]);
        });

        if (! $releasedOrder) {
            return false;
        }

        NotificationService::notifyOperations(
            (int) $releasedOrder->vendor_id,
            'order_confirmed',
            'An order entered the kitchen preparation queue.',
            [NotificationService::KITCHEN],
            $this->notificationMetadata($releasedOrder, $sourceActorType, $sourceActorId),
        );

        return true;
    }

    public function releaseDueOrders(?CarbonInterface $at = null): int
    {
        $at ??= now();
        $released = 0;

        Order::query()
            ->whereNull('kitchen_released_at')
            ->where('payment_received', true)
            ->whereIn('status', Order::ACTIVE_STATUSES)
            ->whereHas('tableScanSession', function ($session) use ($at): void {
                $session->whereIn('type', [OrderSessionService::PICKUP, OrderSessionService::TAKEAWAY])
                    ->where('status', 'active')
                    ->where(function ($schedule) use ($at): void {
                        $schedule->whereNull('scheduled_for')
                            ->orWhere('scheduled_for', '<=', $at->copy()->addMinutes(self::PREPARATION_WINDOW_MINUTES));
                    });
            })
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$released, $at): void {
                foreach ($orders as $order) {
                    if ($this->releaseIfDue($order, 'system', null, $at)) {
                        $released++;
                    }
                }
            });

        return $released;
    }

    public function isDue(Order $order, ?CarbonInterface $at = null): bool
    {
        $at ??= now();
        $order->loadMissing('tableScanSession');
        $session = $order->tableScanSession;

        if (! $session
            || ! $this->orderSessions->isOffPremise($session)
            || ! $order->payment_received
            || ! in_array($order->status, Order::ACTIVE_STATUSES, true)) {
            return false;
        }

        return $session->scheduled_for === null
            || $session->scheduled_for->lte($at->copy()->addMinutes(self::PREPARATION_WINDOW_MINUTES));
    }

    /** @return array<string, mixed> */
    private function notificationMetadata(Order $order, string $sourceActorType, ?int $sourceActorId): array
    {
        $order->loadMissing('tableScanSession.restaurantTable');

        return [
            'resources' => ['orders', 'dashboard', 'notifications'],
            'template' => 'staff.order_confirmed',
            'order_id' => $order->id,
            'order_mode' => $order->tableScanSession?->type ?? $order->order_type,
            'scheduled_for' => $order->tableScanSession?->scheduled_for?->toISOString(),
            'kitchen_released_at' => $order->kitchen_released_at?->toISOString(),
            'severity' => 'urgent',
            'sound' => 'new_order',
            'source_actor_type' => $sourceActorType,
            'source_actor_id' => $sourceActorId,
            'order' => NotificationService::operationalOrderSnapshot($order),
        ];
    }
}
