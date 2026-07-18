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

        $tableIds = TableScanSession::query()
            ->where('status', 'active')
            ->distinct()
            ->pluck('restaurant_table_id');

        foreach ($tableIds as $tableId) {
            $sessions = TableScanSession::query()
                ->where('restaurant_table_id', $tableId)
                ->where('status', 'active')
                ->get();

            if ($sessions->isEmpty() || ! $this->shouldClose($sessions)) {
                continue;
            }

            $customerIds = $sessions->pluck('customer_id')->filter()->unique();

            Order::whereIn('table_scan_session_id', $sessions->pluck('id'))
                ->whereNotIn('status', Order::TERMINAL_STATUSES)
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_reason' => 'Table session expired due to inactivity.',
                ]);

            TableScanSession::query()
                ->whereIn('id', $sessions->pluck('id'))
                ->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                ]);

            NotificationService::notifyCustomers(
                $customerIds,
                'session_expire',
                'Your table session has expired due to inactivity.',
                $sessions->first()?->vendor_id,
                [
                    'template' => 'session.expired',
                    'table_id' => $tableId,
                ],
            );
            NotificationService::notifyOperations(
                (int) $sessions->first()->vendor_id,
                'table_session_changed',
                'A table session expired due to inactivity.',
                [NotificationService::VENDOR, NotificationService::WAITER, NotificationService::KITCHEN],
                [
                    'resources' => ['orders', 'tables', 'dashboard', 'notifications'],
                    'template' => 'staff.table_session_changed',
                    'table_id' => $tableId,
                    'table_label' => $tableId,
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

        $hasRecentActivity = CustomerSessionActivity::query()
            ->whereIn('table_scan_session_id', $sessionIds)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->exists();

        if ($hasRecentActivity) {
            return false;
        }

        $orders = Order::query()
            ->whereIn('table_scan_session_id', $sessionIds)
            ->where('status', '!=', 'cancelled')
            ->get();

        $realOrders = $orders->reject(fn (Order $o) => $o->status === 'draft');

        if ($realOrders->isNotEmpty()) {
            if ($realOrders->contains(fn (Order $order) => ! $order->payment_received)) {
                return false;
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

        $draftOrders = $orders->where('status', 'draft');
        if ($draftOrders->isNotEmpty()) {
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
