<?php

namespace App\Console\Commands;

use App\Models\CartItem;
use App\Models\CustomerSessionActivity;
use App\Models\Order;
use App\Models\TableScanSession;
use App\Services\NotificationService;
use Illuminate\Console\Command;

class CloseStaleTableScanSessions extends Command
{
    protected $signature = 'table-sessions:close-stale';

    protected $description = 'Close stale table scan sessions when no active customer work remains.';

    public function handle(): int
    {
        $closed = 0;

        $sessionGroups = TableScanSession::query()
            ->where('status', 'active')
            ->get()
            ->groupBy(fn (TableScanSession $session) => $session->restaurant_table_id !== null
                ? "table:{$session->vendor_id}:{$session->restaurant_table_id}"
                : "off-premise:{$session->vendor_id}:{$session->type}:{$session->pin}");

        foreach ($sessionGroups as $sessions) {
            $tableId = $sessions->first()?->restaurant_table_id;

            if ($sessions->isEmpty() || ! $this->shouldClose($sessions)) {
                continue;
            }

            $customerIds = $sessions->pluck('customer_id')->filter()->unique();
            $sessionIds = $sessions->pluck('id');
            $groupOrders = Order::query()
                ->whereIn('table_scan_session_id', $sessionIds)
                ->get();
            $orderIds = $groupOrders->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            $completedOffPremise = $tableId === null
                && $groupOrders->where('status', '!=', Order::STATUS_DRAFT)->isNotEmpty()
                && $groupOrders->where('status', '!=', Order::STATUS_DRAFT)->every(
                    fn (Order $order) => $order->payment_received
                        && in_array($order->status, [Order::STATUS_PICKED_UP, Order::STATUS_CANCELLED], true)
                );

            Order::whereIn('table_scan_session_id', $sessionIds)
                ->whereNotIn('status', Order::TERMINAL_STATUSES)
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_reason' => 'Table session expired due to inactivity.',
                ]);

            TableScanSession::query()
                ->whereIn('id', $sessionIds)
                ->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                ]);

            NotificationService::notifyCustomers(
                $customerIds,
                'session_expire',
                $completedOffPremise
                    ? 'Your pickup session has been completed.'
                    : 'Your table session has expired due to inactivity.',
                $sessions->first()?->vendor_id,
                [
                    'template' => $completedOffPremise ? 'session.closed' : 'session.expired',
                    'table_id' => $tableId,
                    'order_ids' => $orderIds,
                    'order_mode' => $sessions->first()?->type,
                ],
            );
            NotificationService::notifyOperations(
                (int) $sessions->first()->vendor_id,
                'table_session_changed',
                $completedOffPremise
                    ? 'A pickup session was completed.'
                    : 'A table session expired due to inactivity.',
                [NotificationService::VENDOR, NotificationService::WAITER, NotificationService::KITCHEN],
                [
                    'resources' => ['orders', 'tables', 'dashboard', 'notifications'],
                    'template' => 'staff.table_session_changed',
                    'table_id' => $tableId,
                    'table_label' => $tableId,
                    'order_ids' => $orderIds,
                    'order_mode' => $sessions->first()?->type,
                    'severity' => 'info',
                    'sound' => null,
                    'source_actor_type' => 'system',
                    'source_actor_id' => null,
                    'table_action' => 'closed',
                ],
                true,
            );

            $closed += $sessions->count();
        }

        $this->info("Closed {$closed} stale table scan session(s).");

        return self::SUCCESS;
    }

    private function shouldClose($sessions): bool
    {
        $sessionIds = $sessions->pluck('id');

        $orders = Order::query()
            ->whereIn('table_scan_session_id', $sessionIds)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->get();
        $realOrders = $orders->reject(fn (Order $order) => $order->status === Order::STATUS_DRAFT);
        $draftOrders = $orders->where('status', Order::STATUS_DRAFT);
        $isOffPremise = $sessions->first()?->restaurant_table_id === null;

        if ($isOffPremise && $realOrders->isNotEmpty()) {
            if ($realOrders->contains(fn (Order $order) => ! $order->payment_received)) {
                return false;
            }

            if ($realOrders->contains(fn (Order $order) => ! in_array(
                $order->status,
                [Order::STATUS_PICKED_UP, Order::STATUS_CANCELLED],
                true,
            ))) {
                return false;
            }

            if ($draftOrders->isEmpty()) {
                return true;
            }

            if ($draftOrders->contains(fn (Order $order) => $order->payment_pending)) {
                return false;
            }
        }

        $hasRecentActivity = CustomerSessionActivity::query()
            ->whereIn('table_scan_session_id', $sessionIds)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($hasRecentActivity) {
            return false;
        }

        if ($realOrders->isNotEmpty()) {
            if ($realOrders->contains(fn (Order $order) => ! $order->payment_received)) {
                return false;
            }

            if ($isOffPremise) {
                $newestDraftUpdate = $draftOrders->max('updated_at');

                return ! $newestDraftUpdate || $newestDraftUpdate->lt(now()->subMinutes(10));
            }

            $paidOrderIds = $realOrders
                ->where('payment_received', true)
                ->pluck('id')
                ->values();

            if ($paidOrderIds->isNotEmpty() && $this->hasUnservedCartItemsForOrders($paidOrderIds->all())) {
                return false;
            }

            return true;
        }

        if ($draftOrders->isNotEmpty()) {
            if ($draftOrders->contains(fn (Order $order) => $order->payment_pending)) {
                return false;
            }

            $newestDraftUpdate = $draftOrders->max('updated_at');

            return ! $newestDraftUpdate || $newestDraftUpdate->lt(now()->subMinutes(10));
        }

        $newestScan = $sessions->max('scanned_at');

        return ! $newestScan || $newestScan->lt(now()->subMinutes(10));
    }

    private function hasUnservedCartItemsForOrders(array $orderIds): bool
    {
        return CartItem::query()
            ->where(function ($query) use ($orderIds) {
                $query->whereIn('order_id', $orderIds);

                foreach ($orderIds as $orderId) {
                    $query->orWhereJsonContains('shared_order_ids', $orderId);
                }
            })
            ->whereNull('served_at')
            ->exists();
    }
}
