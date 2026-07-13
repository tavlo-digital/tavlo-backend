<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\Notification;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use Illuminate\Support\Collection;

class NotificationService
{
    public const VENDOR = 'vendor';

    public const WAITER = 'waiter';

    public const KITCHEN = 'kitchen';

    public static function notifyTableCustomers(
        int $restaurantTableId,
        string $event,
        string $message,
        array $metadata = [],
        bool $notifyOperations = true,
    ): void {
        $sessions = TableScanSession::query()
            ->where('restaurant_table_id', $restaurantTableId)
            ->where('status', 'active')
            ->get(['customer_id', 'vendor_id']);

        self::notifyCustomers(
            $sessions->pluck('customer_id')->filter()->unique(),
            $event,
            $message,
            $sessions->first()?->vendor_id,
            $metadata,
        );

        if ($notifyOperations && $sessions->isNotEmpty()) {
            self::notifyOperationsForTableEvent(
                (int) $sessions->first()->vendor_id,
                $restaurantTableId,
                $event,
                $message,
                $metadata,
            );
        }
    }

    public static function notifyCustomers(
        Collection $customerIds,
        string $event,
        string $message,
        ?int $vendorId = null,
        array $metadata = [],
    ): void {
        $now = now();

        $rows = $customerIds->map(fn (int $id) => [
            'customer_id' => $id,
            'vendor_id' => $vendorId,
            'event' => $event,
            'message' => $message,
            'metadata' => $metadata !== [] ? json_encode($metadata) : null,
            'read' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if (! empty($rows)) {
            Notification::insert($rows);
        }
    }

    public static function notify(string $role, int $id, string $event, string $message): void
    {
        Notification::create([
            "{$role}_id" => $id,
            'event' => $event,
            'message' => $message,
            'read' => false,
        ]);
    }

    /**
     * Persist an operational event for the selected restaurant roles. These rows
     * also drive Supabase Realtime invalidation for connected vendor clients.
     */
    public static function notifyOperations(
        int $vendorId,
        string $event,
        string $message,
        array $audiences,
        array $metadata,
        bool $silent = false,
    ): void {
        $audiences = array_values(array_unique($audiences));
        $now = now();
        $base = [
            'customer_id' => null,
            'vendor_id' => $vendorId,
            'waiter_id' => null,
            'kitchen_id' => null,
            'admin_id' => null,
            'event' => $event,
            'message' => $message,
            'metadata' => json_encode($metadata),
            'read' => $silent,
            'is_silent' => $silent,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $rows = [];

        if (in_array(self::VENDOR, $audiences, true)) {
            $rows[] = $base;
        }

        $staffRoles = array_values(array_intersect(
            [self::WAITER, self::KITCHEN],
            $audiences,
        ));

        if ($staffRoles !== []) {
            TeamMember::query()
                ->where('vendor_id', $vendorId)
                ->where('status', 'active')
                ->whereIn('role', $staffRoles)
                ->get(['id', 'role'])
                ->each(function (TeamMember $member) use (&$rows, $base): void {
                    $rows[] = [
                        ...$base,
                        $member->role === self::WAITER ? 'waiter_id' : 'kitchen_id' => $member->id,
                    ];
                });
        }

        if ($rows !== []) {
            Notification::insert($rows);
        }
    }

    public static function orderSnapshot(Order $order, bool $includeItems = false): array
    {
        $paidBy = $order->relationLoaded('paidBy') ? $order->paidBy : $order->paidBy()->first();

        $snapshot = [
            'order_id' => $order->id,
            'order_public_id' => $order->order_public_id,
            'table_scan_session_id' => $order->table_scan_session_id,
            'status' => $order->status,
            'amount' => (float) $order->amount,
            'tip_amount' => (float) ($order->tip_amount ?? 0),
            'service_fee' => (float) ($order->service_fee ?? 0),
            'currency' => $order->currency,
            'payment_method' => $order->payment_method,
            'payment_pending' => (bool) $order->payment_pending,
            'payment_received' => (bool) $order->payment_received,
            'paid_by' => $paidBy ? [
                'id' => $paidBy->id,
                'name' => trim(($paidBy->first_name ?? '') . ' ' . ($paidBy->last_name ?? '')) ?: 'Guest',
            ] : null,
        ];

        if ($includeItems) {
            $columns = ['id', 'received_at', 'preparing_start_at', 'ready_at', 'served_at', 'picked_up_at'];
            $items = CartItem::where('order_id', $order->id)->get($columns);
            $shared = CartItem::whereJsonContains('shared_order_ids', $order->id)->get($columns);

            $snapshot['items'] = $items->merge($shared)->unique('id')->map(fn (CartItem $ci) => [
                'cart_item_id' => $ci->id,
                'status' => $ci->status(),
                'received_at' => $ci->received_at?->toISOString(),
                'preparing_start_at' => $ci->preparing_start_at?->toISOString(),
                'ready_at' => $ci->ready_at?->toISOString(),
                'served_at' => $ci->served_at?->toISOString(),
            ])->values()->all();
        }

        return $snapshot;
    }

    private static function notifyOperationsForTableEvent(
        int $vendorId,
        int $tableId,
        string $event,
        string $message,
        array $metadata,
    ): void {
        $template = (string) ($metadata['template'] ?? '');
        $table = RestaurantTable::find($tableId, ['id', 'number', 'name']);
        $audiences = [self::VENDOR, self::WAITER];
        $staffTemplate = null;
        $sound = null;
        $severity = 'info';
        $silent = true;

        if ($template === 'order.confirmed') {
            $audiences[] = self::KITCHEN;
            $event = 'order_confirmed';
            $staffTemplate = 'staff.order_confirmed';
            $sound = 'new_order';
            $severity = 'urgent';
            $silent = false;
        } elseif (str_starts_with($template, 'payment.')) {
            $event = 'payment_updated';
            $staffTemplate = 'staff.payment_updated';
            $sound = 'payment';
            $silent = false;
        } elseif (in_array($template, ['session.closed', 'session.expired'], true)) {
            $audiences[] = self::KITCHEN;
            $event = 'table_session_changed';
            $staffTemplate = 'staff.table_session_changed';
            $silent = false;
        } elseif ($template === 'participant.added') {
            $audiences[] = self::KITCHEN;
            $event = 'table_session_changed';
            $staffTemplate = 'staff.table_session_changed';
        }

        self::notifyOperations(
            $vendorId,
            $event,
            $message,
            $audiences,
            [
                ...$metadata,
                'resources' => ['orders', 'tables', 'dashboard', 'notifications'],
                'template' => $staffTemplate,
                'table_id' => $tableId,
                'table_label' => $metadata['table_label'] ?? $table?->name ?? $table?->number ?? $tableId,
                'severity' => $severity,
                'sound' => $sound,
                'source_actor_type' => 'customer',
                'source_actor_id' => $metadata['customer_id'] ?? null,
            ],
            $silent,
        );
    }
}
