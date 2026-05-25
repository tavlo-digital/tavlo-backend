<?php

namespace App\Console\Commands;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\TableScanSession;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CloseStaleTableScanSessions extends Command
{
    protected $signature = 'table-sessions:close-stale';

    protected $description = 'Close stale table scan sessions when no active customer work remains.';

    public function handle(): int
    {
        $cutoff = now()->subMinutes(10);
        $closed = 0;

        TableScanSession::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->chunkById(100, function (Collection $sessions) use ($cutoff, &$closed) {
                foreach ($sessions as $session) {
                    if (! $this->shouldClose($session, $cutoff)) {
                        continue;
                    }

                    $session->update([
                        'status' => 'closed',
                        'closed_at' => now(),
                    ]);

                    $closed++;
                }
            });

        $this->info("Closed {$closed} stale table scan session(s).");

        return self::SUCCESS;
    }

    private function shouldClose(TableScanSession $session, mixed $cutoff): bool
    {
        $orders = Order::query()
            ->where('table_scan_session_id', $session->id)
            ->where('status', '!=', 'cancelled')
            ->get();

        if ($orders->isEmpty()) {
            return ($session->scanned_at ?? $session->created_at)?->lte($cutoff) ?? false;
        }

        $draftOrders = $orders->where('status', 'draft');
        $nonDraftOrders = $orders->where('status', '!=', 'draft');

        if ($nonDraftOrders->isEmpty()) {
            return $draftOrders->every(fn (Order $order) => ($order->updated_at ?? $order->created_at)?->lte($cutoff) ?? false);
        }

        if ($nonDraftOrders->contains(fn (Order $order) => ! $order->payment_received)) {
            return false;
        }

        foreach ($nonDraftOrders as $order) {
            if ($this->hasUnservedCartItemsForOrder($order)) {
                return false;
            }
        }

        return $draftOrders->isEmpty()
            || $draftOrders->every(fn (Order $order) => ($order->updated_at ?? $order->created_at)?->lte($cutoff) ?? false);
    }

    private function hasUnservedCartItemsForOrder(Order $order): bool
    {
        return CartItem::query()
            ->where(function ($query) use ($order) {
                $query->where('order_id', $order->id)
                    ->orWhereJsonContains('shared_order_ids', $order->id);
            })
            ->whereNull('served_at')
            ->exists();
    }
}
