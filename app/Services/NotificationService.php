<?php

namespace App\Services;

use App\Jobs\DeliverCustomerRealtime;
use App\Jobs\DeliverNotification;
use App\Jobs\DeliverOperationalNotification;
use App\Jobs\DeliverOperationalRealtime;
use App\Models\CartItem;
use App\Models\Notification;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class NotificationService
{
    public const VENDOR = 'vendor';

    public const WAITER = 'waiter';

    public const KITCHEN = 'kitchen';

    private const TYPE_TABLE = 'table';

    private const TYPE_CUSTOMERS = 'customers';

    private const TYPE_ROLE = 'role';

    private const TYPE_OPERATIONS = 'operations';

    private const TYPE_OPERATION_ACTOR = 'operation_actor';

    public static function notifyTableCustomers(
        int $restaurantTableId,
        string $event,
        string $message,
        array $metadata = [],
        bool $notifyOperations = true,
    ): void {
        self::dispatch(self::TYPE_TABLE, [
            'restaurant_table_id' => $restaurantTableId,
            'event' => $event,
            'message' => $message,
            'metadata' => $metadata,
            'notify_operations' => $notifyOperations,
        ]);
    }

    public static function notifyCustomers(
        Collection $customerIds,
        string $event,
        string $message,
        ?int $vendorId = null,
        array $metadata = [],
    ): void {
        self::dispatch(self::TYPE_CUSTOMERS, [
            'customer_ids' => $customerIds->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'event' => $event,
            'message' => $message,
            'vendor_id' => $vendorId,
            'metadata' => $metadata,
        ]);
    }

    public static function notify(string $role, int $id, string $event, string $message): void
    {
        self::dispatch(self::TYPE_ROLE, compact('role', 'id', 'event', 'message'));
    }

    /**
     * Persist and broadcast an operational event for the selected restaurant roles.
     */
    public static function notifyOperations(
        int $vendorId,
        string $event,
        string $message,
        array $audiences,
        array $metadata,
        bool $silent = false,
    ): void {
        self::dispatch(self::TYPE_OPERATIONS, [
            'vendor_id' => $vendorId,
            'event' => $event,
            'message' => $message,
            'audiences' => $audiences,
            'metadata' => $metadata,
            'silent' => $silent,
        ]);
    }

    /**
     * Persist an operational event for one authenticated vendor or staff actor.
     * This is used for terminal command acknowledgements that must not fan out
     * to every team member who shares the same role.
     */
    public static function notifyOperationalActor(
        int $vendorId,
        string $role,
        int $actorId,
        string $event,
        string $message,
        array $metadata = [],
        bool $silent = true,
    ): void {
        if (! in_array($role, [self::VENDOR, self::WAITER, self::KITCHEN], true)) {
            throw new \InvalidArgumentException("Unsupported operational actor role [{$role}].");
        }

        if ($role === self::VENDOR && $actorId !== $vendorId) {
            throw new \InvalidArgumentException('A vendor notification must target its owning vendor.');
        }

        self::dispatch(self::TYPE_OPERATION_ACTOR, [
            'vendor_id' => $vendorId,
            'role' => $role,
            'actor_id' => $actorId,
            'event' => $event,
            'message' => $message,
            'metadata' => $metadata,
            'silent' => $silent,
        ]);
    }

    /**
     * Execute a queued delivery. Public for DeliverNotification only.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function deliver(string $deliveryId, string $type, array $payload): void
    {
        match ($type) {
            self::TYPE_TABLE => self::deliverTableCustomers($deliveryId, $payload),
            self::TYPE_CUSTOMERS => self::deliverCustomers(
                $deliveryId,
                collect($payload['customer_ids']),
                $payload['event'],
                $payload['message'],
                $payload['vendor_id'],
                $payload['metadata'],
            ),
            self::TYPE_ROLE => self::deliverRole($deliveryId, $payload),
            // Compatibility for operational jobs queued before the dedicated
            // vendor notification queue was deployed.
            self::TYPE_OPERATIONS => self::deliverOperational($deliveryId, $type, $payload),
            default => throw new \InvalidArgumentException("Unknown notification delivery type [{$type}]."),
        };
    }

    /**
     * Persist operational recipient rows, then enqueue their Pusher delivery.
     * Public for DeliverOperationalNotification only.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function deliverOperational(string $deliveryId, string $type, array $payload): void
    {
        match ($type) {
            self::TYPE_TABLE => self::deliverTableOperations($deliveryId, $payload),
            self::TYPE_OPERATIONS => self::deliverOperations(
                $deliveryId,
                $payload['vendor_id'],
                $payload['event'],
                $payload['message'],
                $payload['audiences'],
                $payload['metadata'],
                $payload['silent'],
            ),
            self::TYPE_OPERATION_ACTOR => self::deliverOperationalActor(
                $deliveryId,
                $payload['vendor_id'],
                $payload['role'],
                $payload['actor_id'],
                $payload['event'],
                $payload['message'],
                $payload['metadata'],
                $payload['silent'],
            ),
            default => throw new \InvalidArgumentException("Unknown operational delivery type [{$type}]."),
        };

        if (
            (bool) config('services.realtime.vendor_enabled', false)
            && self::operationalRowsExist($deliveryId)
        ) {
            DeferredQueueDispatcher::dispatch(new DeliverOperationalRealtime($deliveryId));
        }
    }

    /** @param array<string, mixed> $payload */
    private static function deliverTableCustomers(string $deliveryId, array $payload): void
    {
        $sessions = TableScanSession::query()
            ->where('restaurant_table_id', $payload['restaurant_table_id'])
            ->where('status', 'active')
            ->get(['customer_id', 'vendor_id']);

        self::deliverCustomers(
            $deliveryId,
            $sessions->pluck('customer_id')->filter()->unique(),
            $payload['event'],
            $payload['message'],
            $sessions->first()?->vendor_id,
            $payload['metadata'],
        );

        // Jobs queued before operational delivery was split onto its own
        // queue do not have this marker, so preserve their fan-out on deploy.
        if (
            ($payload['notify_operations'] ?? false)
            && ! ($payload['operations_separated'] ?? false)
            && $sessions->isNotEmpty()
        ) {
            self::deliverOperational($deliveryId, self::TYPE_TABLE, $payload);
        }
    }

    /** @param array<string, mixed> $payload */
    private static function deliverTableOperations(string $deliveryId, array $payload): void
    {
        $table = RestaurantTable::query()
            ->join('table_scan_sessions', 'table_scan_sessions.restaurant_table_id', '=', 'restaurant_tables.id')
            ->where('restaurant_tables.id', $payload['restaurant_table_id'])
            ->where('table_scan_sessions.status', 'active')
            ->select([
                'restaurant_tables.id',
                'restaurant_tables.vendor_id',
                'restaurant_tables.number',
                'restaurant_tables.name',
            ])
            ->first();

        if (! $table) {
            return;
        }

        self::deliverOperationsForTableEvent(
            $deliveryId,
            $table,
            $payload['event'],
            $payload['message'],
            $payload['metadata'],
        );
    }

    private static function deliverCustomers(
        string $deliveryId,
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
            'delivery_key' => "{$deliveryId}:customer:{$id}",
            'event' => $event,
            'message' => $message,
            'metadata' => json_encode(self::deliveryMetadata($deliveryId, $metadata)),
            'read' => false,
            'is_silent' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        if (! empty($rows)) {
            Notification::insertOrIgnore($rows);
        }
    }

    /** @param array<string, mixed> $payload */
    private static function deliverRole(string $deliveryId, array $payload): void
    {
        Notification::insertOrIgnore([[
            'delivery_key' => "{$deliveryId}:{$payload['role']}:{$payload['id']}",
            "{$payload['role']}_id" => $payload['id'],
            'event' => $payload['event'],
            'message' => $payload['message'],
            'read' => false,
            'is_silent' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);
    }

    private static function deliverOperations(
        string $deliveryId,
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
            'delivery_key' => null,
            'customer_id' => null,
            'vendor_id' => $vendorId,
            'waiter_id' => null,
            'kitchen_id' => null,
            'admin_id' => null,
            'event' => $event,
            'message' => $message,
            'metadata' => json_encode(self::deliveryMetadata($deliveryId, $metadata)),
            'read' => $silent,
            'is_silent' => $silent,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $rows = [];

        if (in_array(self::VENDOR, $audiences, true)) {
            $rows[] = [
                ...$base,
                'delivery_key' => "{$deliveryId}:vendor:{$vendorId}",
            ];
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
                ->each(function (TeamMember $member) use (&$rows, $base, $deliveryId): void {
                    $rows[] = [
                        ...$base,
                        'delivery_key' => "{$deliveryId}:{$member->role}:{$member->id}",
                        $member->role === self::WAITER ? 'waiter_id' : 'kitchen_id' => $member->id,
                    ];
                });
        }

        if ($rows !== []) {
            Notification::insertOrIgnore($rows);
        }
    }

    private static function deliverOperationalActor(
        string $deliveryId,
        int $vendorId,
        string $role,
        int $actorId,
        string $event,
        string $message,
        array $metadata,
        bool $silent,
    ): void {
        $row = [
            'delivery_key' => "{$deliveryId}:{$role}:{$actorId}",
            'customer_id' => null,
            'vendor_id' => $vendorId,
            'waiter_id' => $role === self::WAITER ? $actorId : null,
            'kitchen_id' => $role === self::KITCHEN ? $actorId : null,
            'admin_id' => null,
            'event' => $event,
            'message' => $message,
            'metadata' => json_encode(self::deliveryMetadata($deliveryId, $metadata)),
            'read' => $silent,
            'is_silent' => $silent,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        Notification::insertOrIgnore([$row]);
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
                'name' => trim(($paidBy->first_name ?? '').' '.($paidBy->last_name ?? '')) ?: 'Guest',
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

    private static function deliverOperationsForTableEvent(
        string $deliveryId,
        RestaurantTable $table,
        string $event,
        string $message,
        array $metadata,
    ): void {
        $template = (string) ($metadata['template'] ?? '');
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

        self::deliverOperations(
            $deliveryId,
            (int) $table->vendor_id,
            $event,
            $message,
            $audiences,
            [
                ...$metadata,
                'resources' => ['orders', 'tables', 'dashboard', 'notifications'],
                'template' => $staffTemplate,
                'table_id' => $table->id,
                'table_label' => $metadata['table_label'] ?? $table->name ?? $table->number ?? $table->id,
                'severity' => $severity,
                'sound' => $sound,
                'source_actor_type' => 'customer',
                'source_actor_id' => $metadata['customer_id'] ?? null,
            ],
            $silent,
        );
    }

    /** @param array<string, mixed> $payload */
    private static function dispatch(string $type, array $payload): void
    {
        $deliveryId = (string) Str::uuid7();
        $createdAt = now()->toISOString();
        $payload['metadata'] = isset($payload['metadata'])
            ? [
                ...$payload['metadata'],
                'event_version' => (int) floor(microtime(true) * 1_000_000),
            ]
            : [];
        $payload['created_at'] = $createdAt;

        if (
            (bool) config('services.realtime.customer_enabled', false)
            && in_array($type, [self::TYPE_TABLE, self::TYPE_CUSTOMERS], true)
        ) {
            DeferredQueueDispatcher::dispatch(
                new DeliverCustomerRealtime($deliveryId, $type, $payload),
            );
        }

        if (in_array($type, [self::TYPE_OPERATIONS, self::TYPE_OPERATION_ACTOR], true)) {
            DeferredQueueDispatcher::dispatch(
                new DeliverOperationalNotification($deliveryId, $type, $payload),
            );

            return;
        }

        if ($type === self::TYPE_TABLE && ($payload['notify_operations'] ?? false)) {
            DeferredQueueDispatcher::dispatch(
                new DeliverOperationalNotification($deliveryId, $type, $payload),
            );
            $payload['operations_separated'] = true;
        }

        $job = new DeliverNotification($deliveryId, $type, $payload);

        if ((bool) config('services.notifications.queue_enabled', false)) {
            DeferredQueueDispatcher::dispatch($job);

            return;
        }

        $job->handle();
    }

    private static function operationalRowsExist(string $deliveryId): bool
    {
        return Notification::query()
            ->where('delivery_key', 'like', "{$deliveryId}:%")
            ->whereNull('customer_id')
            ->whereNull('admin_id')
            ->where(function ($query): void {
                $query->whereNotNull('waiter_id')
                    ->orWhereNotNull('kitchen_id')
                    ->orWhere(function ($vendorQuery): void {
                        $vendorQuery->whereNotNull('vendor_id')
                            ->whereNull('waiter_id')
                            ->whereNull('kitchen_id');
                    });
            })
            ->exists();
    }

    private static function deliveryMetadata(string $deliveryId, array $metadata): array
    {
        return [
            ...$metadata,
            'event_id' => $deliveryId,
        ];
    }
}
