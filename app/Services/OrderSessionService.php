<?php

namespace App\Services;

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
    }
}
