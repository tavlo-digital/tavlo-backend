<?php

namespace App\Services;

use App\Models\Notification;
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
