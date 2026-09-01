<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TableScanSession;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OrderSessionService
{
    public const DINE_IN = 'dine_in';

    public const PICKUP = 'pickup';

    public const TAKEAWAY = 'takeaway';

    public function mode(Request $request): string
    {
        return match (strtolower(trim((string) $request->header('X-Order-Mode')))) {
            self::PICKUP => self::PICKUP,
            self::TAKEAWAY => self::TAKEAWAY,
            'dine-in', self::DINE_IN => self::DINE_IN,
            default => self::DINE_IN,
        };
    }

    public function isOffPremise(TableScanSession|string $sessionOrMode): bool
    {
        $mode = $sessionOrMode instanceof TableScanSession
            ? $sessionOrMode->type
            : $sessionOrMode;

        return in_array($mode, [self::PICKUP, self::TAKEAWAY], true);
    }

    /**
     * The identity staff group an order under: the PIN party for off-premise,
     * the table for dine-in. Null when neither applies, so clients fall back to
     * the order itself rather than lumping unrelated orders together.
     *
     * Lives here because both the orders list and the realtime push have to
     * produce the same key. When only one of them did, an order that arrived
     * live opened a second card for a party already on screen.
     */
    public function sessionGroupKeyFor(Order $order): ?string
    {
        $session = $order->tableScanSession;

        if (! $session) {
            return null;
        }

        if ($this->isOffPremise($session)) {
            return $session->pin
                ? "offpremise:{$session->vendor_id}:{$session->type}:{$session->pin}"
                : "session:{$session->id}";
        }

        return $session->restaurant_table_id ? "table:{$session->restaurant_table_id}" : null;
    }

    public function applyMode(Builder $query, Request $request): Builder
    {
        return $query->where('type', $this->mode($request));
    }

    public function activeForCustomer(int $customerId, Request $request): ?TableScanSession
    {
        return $this->applyMode(
            TableScanSession::query()
                ->where('customer_id', $customerId)
                ->where('status', 'active'),
            $request,
        )
            ->latest('scanned_at')
            ->latest('id')
            ->first();
    }

    public function groupQuery(TableScanSession $session): Builder
    {
        $query = TableScanSession::query()
            ->where('vendor_id', $session->vendor_id)
            ->where('type', $session->type)
            ->where('status', 'active');

        if ($this->isOffPremise($session)) {
            return $query->where('pin', $session->pin);
        }

        return $query->where('restaurant_table_id', $session->restaurant_table_id);
    }

    /** @return array<int, int> */
    public function groupSessionIds(TableScanSession $session): array
    {
        return $this->groupQuery($session)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return Collection<int, int> */
    public function groupCustomerIds(TableScanSession $session): Collection
    {
        return $this->groupQuery($session)
            ->whereNotNull('customer_id')
            ->pluck('customer_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    public function notifyCustomers(
        TableScanSession $session,
        string $event,
        string $message,
        array $metadata = [],
        bool $notifyOperations = true,
    ): void {
        if (! $this->isOffPremise($session)) {
            NotificationService::notifyTableCustomers(
                $session->restaurant_table_id,
                $event,
                $message,
                $metadata,
                $notifyOperations,
            );

            return;
        }

        NotificationService::notifyCustomers(
            $this->groupCustomerIds($session),
            $event,
            $message,
            (int) $session->vendor_id,
            [
                ...$metadata,
                'order_mode' => $session->type,
                'session_pin' => $session->pin,
            ],
        );

        if (! $notifyOperations) {
            return;
        }

        // Unlike a dine-in event, an off-premise event has no restaurant table
        // through which NotificationService can fan out to operations. Without
        // this explicit leg, a cash request reached the other guests but never
        // invalidated the waiter's open pickup card, leaving its prior paid
        // orders on screen until a manual reload.
        $template = (string) ($metadata['template'] ?? '');
        $isPaymentUpdate = str_starts_with($template, 'payment.');

        NotificationService::notifyOperations(
            (int) $session->vendor_id,
            $event,
            $message,
            [NotificationService::VENDOR, NotificationService::WAITER],
            [
                ...$metadata,
                'resources' => ['orders', 'tables', 'dashboard', 'notifications'],
                'template' => $isPaymentUpdate ? 'staff.payment_updated' : null,
                'order_mode' => $session->type,
                'session_pin' => $session->pin,
                'severity' => 'info',
                'sound' => $isPaymentUpdate ? 'payment' : null,
                'source_actor_type' => 'customer',
                'source_actor_id' => $metadata['customer_id'] ?? null,
            ],
            ! $isPaymentUpdate,
        );
    }
}
