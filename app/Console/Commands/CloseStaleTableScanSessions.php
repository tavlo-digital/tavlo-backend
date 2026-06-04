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

            TableScanSession::query()
                ->whereIn('id', $sessions->pluck('id'))
                ->update([
                    'status' => 'closed',
                    'closed_at' => now(),
                ]);

            NotificationService::notifyCustomers($customerIds, 'session_expire', 'Your table session has expired due to inactivity.');

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
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->get();

        if ($orders->contains(fn (Order $order) => ! $order->payment_received)) {
            return false;
        }

        $paidOrderIds = $orders
            ->where('payment_received', true)
            ->pluck('id')
            ->values();

        if ($paidOrderIds->isNotEmpty() && $this->hasUnservedCartItemsForOrders($paidOrderIds->all())) {
            return false;
        }

        return true;
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
