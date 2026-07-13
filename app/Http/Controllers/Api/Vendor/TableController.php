<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use App\Models\VendorTakeawayQr;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TableController extends Controller
{
    /**
     * GET /api/vendor/{vendorId}/tables
     */
    public function index(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $tables = $vendor->restaurantTables()->orderBy('number')->get();

        $activeTableIds = TableScanSession::where('vendor_id', $vendor->id)
            ->where('status', 'active')
            ->pluck('restaurant_table_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->flip()
            ->all();

        $unpaidTableIds = TableScanSession::where('table_scan_sessions.vendor_id', $vendor->id)
            ->join('orders', 'orders.table_scan_session_id', '=', 'table_scan_sessions.id')
            ->where('table_scan_sessions.status', 'active')
            ->where('orders.payment_pending', true)
            ->where('orders.payment_received', false)
            ->pluck('table_scan_sessions.restaurant_table_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->flip()
            ->all();

        $result = $tables->map(fn (RestaurantTable $t) =>
            $this->formatTable($t, $this->deriveStatus($t->id, $activeTableIds, $unpaidTableIds))
        );

        return response()->json($result);
    }

    /**
     * POST /api/vendor/{vendorId}/tables
     */
    public function store(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1'],
            'name'   => ['nullable', 'string', 'max:100'],
        ]);

        $table = $vendor->restaurantTables()->create([
            'number'       => $data['number'],
            'name'         => $data['name'] ?? ("Table " . $data['number']),
            'qr_token'     => RestaurantTable::generateQrToken(),
            'is_active'    => true,
            'qr_created_at' => now(),
        ]);
        $this->notifyTableChanged($request, $vendor, $table);

        return response()->json($this->formatTable($table), 201);
    }

    /**
     * PATCH /api/vendor/{vendorId}/tables/{tableId}
     */
    public function update(Request $request, string $vendorId, string $tableId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $table = $vendor->restaurantTables()->findOrFail($tableId);

        $data = $request->validate([
            'name'      => ['sometimes', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $table->update($data);
        $this->notifyTableChanged($request, $vendor, $table);

        return response()->json($this->formatTable($table->fresh()));
    }

    /**
     * DELETE /api/vendor/{vendorId}/tables/{tableId}
     */
    public function destroy(Request $request, string $vendorId, string $tableId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $table = $vendor->restaurantTables()->findOrFail($tableId);

        $hasActiveOrders = Order::where('vendor_id', $vendor->id)
            ->where('table_number', (string) $table->number)
            ->whereIn('status', Order::ACTIVE_STATUSES)
            ->exists();

        if ($hasActiveOrders) {
            return response()->json(['message' => 'Cannot delete table with active orders'], 409);
        }

        $table->delete();
        $this->notifyTableChanged($request, $vendor, $table);

        return response()->json(['message' => 'Table deleted']);
    }

    /**
     * POST /api/vendor/{vendorId}/tables/regenerate-all
     * Regenerate QR tokens for all tables.
     */
    public function regenerateAll(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $tablesWithActiveSessions = TableScanSession::where('status', 'active')
            ->whereIn('restaurant_table_id', $vendor->restaurantTables()->pluck('id'))
            ->pluck('restaurant_table_id')
            ->unique();

        $vendor->restaurantTables()->each(function (RestaurantTable $table) use ($tablesWithActiveSessions) {
            if (! $tablesWithActiveSessions->contains($table->id)) {
                $table->refreshQr();
            }
        });

        $tables = $vendor->restaurantTables()
            ->orderBy('number')
            ->get()
            ->map(fn (RestaurantTable $t) => $this->formatTable($t));
        $this->notifyTableChanged($request, $vendor);

        return response()->json($tables);
    }

    /**
     * POST /api/vendor/{vendorId}/tables/{tableId}/refresh-qr
     */
    public function refreshQR(Request $request, string $vendorId, string $tableId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $table = $vendor->restaurantTables()->findOrFail($tableId);

        $activeSessions = TableScanSession::where('restaurant_table_id', $table->id)
            ->where('status', 'active')
            ->count();

        if ($activeSessions > 0) {
            return response()->json([
                'message' => 'Cannot regenerate QR while the table has active sessions. Close the table first.',
                'active_sessions' => $activeSessions,
            ], 409);
        }

        $table->refreshQr();

        return response()->json($this->formatTable($table->fresh()));
    }

    /**
     * POST /api/vendor/{vendorId}/tables/{tableId}/scan
     */
    public function recordScan(Request $request, string $vendorId, string $tableId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $table = $vendor->restaurantTables()->findOrFail($tableId);
        $token = $request->query('token', $request->input('token'));

        if (! $table->is_active || ($token && $token !== $table->qr_token)) {
            return response()->json(['message' => 'This QR code is no longer valid'], 410);
        }

        $table->update(['last_scanned_at' => now()]);

        return response()->json([
            'message'     => 'Scan recorded',
            'vendorId'    => $vendor->vendor_public_id,
            'tableId'     => (string) $table->id,
            'tableName'   => $table->name,
            'tableNumber' => $table->number,
        ]);
    }

    /**
     * GET /api/vendor/{vendorId}/tables/takeaway-qr
     */
    public function takeawayQR(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $qr = $this->getOrCreateTakeawayQr($vendor);

        return response()->json($this->formatTakeawayQr($qr));
    }

    /**
     * POST /api/vendor/{vendorId}/tables/takeaway-qr/refresh
     */
    public function refreshTakeawayQR(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $qr = $this->getOrCreateTakeawayQr($vendor);
        $qr->update([
            'qr_token'            => $this->generateTakeawayToken(),
            'last_regenerated_at' => now(),
        ]);

        return response()->json($this->formatTakeawayQr($qr->fresh()));
    }

    /**
     * POST /api/vendor/{vendorId}/takeaway/scan
     */
    public function recordTakeawayScan(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $qr = VendorTakeawayQr::where('vendor_id', $vendor->id)->first();
        $token = $request->query('token', $request->input('token'));

        if (! $qr || ($token && $token !== $qr->qr_token)) {
            return response()->json(['message' => 'This QR code is no longer valid'], 410);
        }

        $qr->update(['last_scanned_at' => now()]);

        return response()->json([
            'message'  => 'Scan recorded',
            'vendorId' => $vendor->vendor_public_id,
            'type'     => 'takeaway',
        ]);
    }

    /**
     * POST /api/vendor/{vendorId}/tables/sync
     * Sync the number of tables to match the vendor settings.
     */
    public function sync(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $desired = $request->validate([
            'count'  => ['required', 'integer', 'min:0', 'max:500'],
            'prefix' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);
        $count  = $desired['count'];
        $prefix = $desired['prefix'] ?? 'T';

        $existing = $vendor->restaurantTables()->orderBy('number')->get();
        $currentCount = $existing->count();

        if ($count > $currentCount) {
            // Add missing tables
            for ($n = $currentCount + 1; $n <= $count; $n++) {
                $vendor->restaurantTables()->create([
                    'number'        => $n,
                    'name'          => "{$prefix}{$n}",
                    'qr_token'      => RestaurantTable::generateQrToken(),
                    'is_active'     => true,
                    'qr_created_at' => now(),
                ]);
            }
        } elseif ($count < $currentCount) {
            // Remove excess tables (highest numbers first)
            $vendor->restaurantTables()
                ->orderByDesc('number')
                ->limit($currentCount - $count)
                ->get()
                ->each->delete();
        }

        $tables = $vendor->restaurantTables()
            ->orderBy('number')
            ->get()
            ->map(fn (RestaurantTable $t) => $this->formatTable($t));

        return response()->json($tables);
    }

    /**
     * POST /api/vendor/{vendorId}/tables/{tableId}/close-session
     */
    public function closeSession(Request $request, string $vendorId, string $tableId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'force' => ['sometimes', 'boolean'],
        ]);

        $table = $vendor->restaurantTables()->findOrFail($tableId);

        $result = DB::transaction(function () use ($vendor, $table, $data) {
            $sessions = TableScanSession::where('vendor_id', $vendor->id)
                ->where('restaurant_table_id', $table->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            if ($sessions->isEmpty()) {
                return ['outcome' => 'not_found'];
            }

            $orders = Order::whereIn('table_scan_session_id', $sessions->pluck('id'))
                ->whereNotIn('status', ['cancelled', 'draft'])
                ->lockForUpdate()
                ->get();

            $paymentSummary = $this->paymentSummary($orders);
            $fulfillmentOrderIds = $orders->pluck('id')->values();

            $unfinishedItems = collect();

            if ($fulfillmentOrderIds->isNotEmpty()) {
                $unfinishedItems = CartItem::query()
                    ->where(function ($query) use ($fulfillmentOrderIds) {
                        $query->whereIn('order_id', $fulfillmentOrderIds);

                        foreach ($fulfillmentOrderIds as $orderId) {
                            $query->orWhereJsonContains('shared_order_ids', $orderId);
                        }
                    })
                    ->whereNull('served_at')
                    ->lockForUpdate()
                    ->get(['id', 'order_id', 'shared_order_ids']);
            }

            if ($unfinishedItems->isNotEmpty()) {
                $unfinishedOrderCount = $fulfillmentOrderIds
                    ->filter(function ($orderId) use ($unfinishedItems) {
                        return $unfinishedItems->contains(function (CartItem $item) use ($orderId) {
                            $sharedOrderIds = is_array($item->shared_order_ids) ? $item->shared_order_ids : [];

                            return (string) $item->order_id === (string) $orderId
                                || in_array((int) $orderId, array_map('intval', $sharedOrderIds), true);
                        });
                    })
                    ->count();

                return [
                    'outcome' => 'unfinished_items',
                    'paymentSummary' => $paymentSummary,
                    'fulfillmentSummary' => [
                        'unfinishedOrdersCount' => $unfinishedOrderCount,
                        'unservedItemsCount' => $unfinishedItems->count(),
                    ],
                ];
            }

            if ($paymentSummary['remainingAmount'] > 0 && ! ($data['force'] ?? false)) {
                return [
                    'outcome' => 'unpaid_balance',
                    'paymentSummary' => $paymentSummary,
                ];
            }

            Order::whereIn('table_scan_session_id', $sessions->pluck('id'))
                ->whereNotIn('status', Order::TERMINAL_STATUSES)
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_reason' => 'Table session closed by staff.',
                ]);

            TableScanSession::whereIn('id', $sessions->pluck('id'))->update([
                'status' => 'closed',
                'closed_at' => now(),
            ]);

            return [
                'outcome' => 'closed',
                'sessions' => $sessions,
                'paymentSummary' => $paymentSummary,
            ];
        });

        if ($result['outcome'] === 'not_found') {
            return response()->json(['message' => 'No active table session found.'], 404);
        }

        if ($result['outcome'] === 'unfinished_items') {
            return response()->json([
                'message' => 'This table still has unfinished order items.',
                'code' => 'unfinished_items',
                'fulfillmentSummary' => $result['fulfillmentSummary'],
                'paymentSummary' => $result['paymentSummary'],
            ], 409);
        }

        if ($result['outcome'] === 'unpaid_balance') {
            return response()->json([
                'message' => 'This table still has unpaid balances.',
                'code' => 'unpaid_balance',
                'paymentSummary' => $result['paymentSummary'],
            ], 409);
        }

        $table->update(['call_waiter_at' => null]);

        // Same customer-facing event as the customer-close and stale-expiry
        // paths, so guests learn their session ended when staff closes the table.
        NotificationService::notifyCustomers(
            $result['sessions']->pluck('customer_id')->filter()->unique()->values(),
            'session_expire',
            'Your table session has been closed.',
            $vendor->id,
            [
                'template' => 'session.closed',
                'table_id' => $table->id,
            ],
        );

        $this->notifyTableChanged($request, $vendor, $table, false);

        return response()->json([
            'message' => 'Table session closed',
            'table' => $this->formatTable($table->fresh(), 'idle'),
            'closedSessionIds' => $result['sessions']->pluck('id')->map(fn ($id) => (string) $id)->values(),
            'paymentSummary' => $result['paymentSummary'],
        ]);
    }

    /**
     * POST /api/vendor/{vendorId}/tables/{tableId}/session
     * Staff starts a table session (no customer scan). Idempotent: returns
     * the existing active session's PIN when one is already open.
     */
    public function createSession(Request $request, string $vendorId, string $tableId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $table = $vendor->restaurantTables()->findOrFail($tableId);

        if (! $table->is_active) {
            return response()->json([
                'message' => 'This table is inactive.',
                'code' => 'inactive_table',
            ], 422);
        }

        $result = DB::transaction(function () use ($vendor, $table) {
            // The waiter's own session slot is the customer-less one; customer
            // sessions at the table are other "people" and are never reused.
            $activeSessions = TableScanSession::where('vendor_id', $vendor->id)
                ->where('restaurant_table_id', $table->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            $existing = $activeSessions->firstWhere('customer_id', null);

            if ($existing) {
                return ['session' => $existing, 'created' => false];
            }

            $session = TableScanSession::create([
                'vendor_id' => $vendor->id,
                'restaurant_table_id' => $table->id,
                'customer_id' => null,
                // Share the table's existing PIN, like customers joining via PIN.
                'pin' => $activeSessions->first()?->pin ?? TableScanSession::generateUniquePin(),
                'status' => 'active',
                'scanned_at' => now(),
            ]);

            return ['session' => $session, 'created' => true];
        });

        if ($result['created']) {
            $this->notifyTableChanged($request, $vendor, $table);
        }

        return response()->json([
            'message' => $result['created'] ? 'Session started.' : 'Table already has an active session.',
            'session_id' => (string) $result['session']->id,
            'pin' => $result['session']->pin,
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
    }

    public function dismissCall(Request $request, string $vendorId, string $tableId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);
        $table = $vendor->restaurantTables()->findOrFail($tableId);

        $table->update(['call_waiter_at' => null]);

        return response()->json(['message' => 'Call dismissed.']);
    }

    public function transfer(Request $request, string $vendorId, string $tableId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $data = $request->validate([
            'target_table_id' => ['required', 'integer'],
        ]);

        if ((string) $tableId === (string) $data['target_table_id']) {
            return response()->json([
                'message' => 'Source and target tables must be different.',
                'code' => 'same_table',
            ], 422);
        }

        $result = DB::transaction(function () use ($vendor, $tableId, $data) {
            $tables = $vendor->restaurantTables()
                ->whereIn('id', [$tableId, $data['target_table_id']])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (RestaurantTable $table) => (string) $table->id);

            $sourceTable = $tables->get((string) $tableId);
            $targetTable = $tables->get((string) $data['target_table_id']);

            if (! $sourceTable || ! $targetTable) {
                return ['outcome' => 'table_not_found'];
            }

            if (! $targetTable->is_active) {
                return ['outcome' => 'inactive_target'];
            }

            $targetHasActiveSessions = TableScanSession::where('vendor_id', $vendor->id)
                ->where('restaurant_table_id', $targetTable->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first(['id']) !== null;

            if ($targetHasActiveSessions) {
                return [
                    'outcome' => 'target_occupied',
                    'targetTable' => $targetTable,
                ];
            }

            $sessions = TableScanSession::where('vendor_id', $vendor->id)
                ->where('restaurant_table_id', $sourceTable->id)
                ->where('status', 'active')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($sessions->isEmpty()) {
                return ['outcome' => 'no_active_session'];
            }

            $sessionIds = $sessions->pluck('id');
            $ordersUpdated = Order::where('vendor_id', $vendor->id)
                ->whereIn('table_scan_session_id', $sessionIds)
                ->update(['table_number' => (string) $targetTable->number]);

            TableScanSession::whereIn('id', $sessionIds)
                ->update(['restaurant_table_id' => $targetTable->id]);

            $sourceCallAt = $sourceTable->call_waiter_at;
            $sourceTable->update(['call_waiter_at' => null]);

            if ($sourceCallAt && ! $targetTable->call_waiter_at) {
                $targetTable->update(['call_waiter_at' => $sourceCallAt]);
            }

            return [
                'outcome' => 'transferred',
                'sourceTable' => $sourceTable,
                'targetTable' => $targetTable,
                'sessionIds' => $sessionIds,
                'ordersUpdated' => $ordersUpdated,
            ];
        });

        if ($result['outcome'] === 'table_not_found') {
            return response()->json(['message' => 'Source or target table was not found.'], 404);
        }

        if ($result['outcome'] === 'inactive_target') {
            return response()->json([
                'message' => 'The selected target table is inactive.',
                'code' => 'inactive_target',
            ], 409);
        }

        if ($result['outcome'] === 'target_occupied') {
            $targetTable = $result['targetTable'];
            $targetLabel = $targetTable->name ?? "Table #{$targetTable->number}";

            return response()->json([
                'message' => "{$targetLabel} is occupied. Select an empty table.",
                'code' => 'target_table_occupied',
                'target_table_id' => (string) $targetTable->id,
            ], 409);
        }

        if ($result['outcome'] === 'no_active_session') {
            return response()->json(['message' => 'No active session on this table.'], 404);
        }

        $sourceTable = $result['sourceTable'];
        $targetTable = $result['targetTable'];

        $sourceLabel = $sourceTable->name ?? "Table #{$sourceTable->number}";
        $targetLabel = $targetTable->name ?? "Table #{$targetTable->number}";

        NotificationService::notifyTableCustomers(
            $targetTable->id,
            'table_transfer',
            "Your table was moved from {$sourceLabel} to {$targetLabel}.",
            [
                'template' => 'session.transferred',
                'source_table_id' => $sourceTable->id,
                'source_table_label' => $sourceLabel,
                'target_table_id' => $targetTable->id,
                'target_table_label' => $targetLabel,
            ],
            false,
        );

        NotificationService::notifyOperations(
            $vendor->id,
            'table_session_changed',
            "{$sourceLabel} transferred to {$targetLabel}.",
            [NotificationService::VENDOR, NotificationService::WAITER, NotificationService::KITCHEN],
            [
                'resources' => ['orders', 'tables', 'dashboard', 'notifications'],
                'template' => 'staff.table_session_changed',
                'table_id' => $targetTable->id,
                'table_label' => $targetLabel,
                'severity' => 'info',
                'sound' => null,
                'source_actor_type' => $request->user() instanceof TeamMember ? 'team_member' : 'vendor',
                'source_actor_id' => $request->user()?->id,
            ],
        );

        return response()->json([
            'message' => "Transferred to {$targetLabel}.",
            'source_table_id' => (string) $sourceTable->id,
            'target_table_id' => (string) $targetTable->id,
            'session_ids' => $result['sessionIds']->map(fn ($id) => (string) $id)->values(),
            'sessions_transferred' => $result['sessionIds']->count(),
            'orders_updated' => $result['ordersUpdated'],
        ]);
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

    private function paymentSummary(Collection $orders): array
    {
        $total = round((float) $orders->sum(fn (Order $order) => (float) $order->amount), 2);
        $paid = round((float) $orders
            ->filter(fn (Order $order) => (bool) $order->payment_received)
            ->sum(fn (Order $order) => (float) $order->amount), 2);

        return [
            'totalAmount' => $total,
            'paidAmount' => $paid,
            'remainingAmount' => round(max(0, $total - $paid), 2),
            'cashPendingOrders' => $orders
                ->filter(fn (Order $order) => $order->payment_method === 'cash'
                    && (bool) $order->payment_pending
                    && ! (bool) $order->payment_received)
                ->count(),
            'ordersCount' => $orders->count(),
        ];
    }

    private function notifyTableChanged(
        Request $request,
        Vendor $vendor,
        ?RestaurantTable $table = null,
        bool $silent = true,
    ): void {
        $actor = $request->user();
        NotificationService::notifyOperations(
            $vendor->id,
            'table_session_changed',
            $table ? "Table {$table->name} was updated." : 'Restaurant tables were updated.',
            [NotificationService::VENDOR, NotificationService::WAITER, NotificationService::KITCHEN],
            [
                'resources' => ['orders', 'tables', 'dashboard', 'notifications'],
                'template' => $silent ? null : 'staff.table_session_changed',
                'table_id' => $table?->id,
                'table_label' => $table?->name ?? $table?->number,
                'severity' => 'info',
                'sound' => null,
                'source_actor_type' => $actor instanceof TeamMember ? 'team_member' : 'vendor',
                'source_actor_id' => $actor?->id,
            ],
            $silent,
        );
    }

    private function formatTable(RestaurantTable $table, string $status = 'idle'): array
    {
        return [
            'id'            => (string) $table->id,
            'number'        => $table->number,
            'name'          => $table->name,
            'qrToken'       => $table->qr_token,
            'isActive'      => (bool) $table->is_active,
            'status'        => $status,
            'qrCreatedAt'   => $table->qr_created_at?->toISOString(),
            'lastScannedAt' => $table->last_scanned_at?->toISOString(),
        ];
    }

    private function formatTakeawayQr(VendorTakeawayQr $qr): array
    {
        return [
            'id'                => (string) $qr->id,
            'qrToken'           => $qr->qr_token,
            'qrCreatedAt'       => $qr->created_at?->toISOString(),
            'lastRegeneratedAt' => $qr->last_regenerated_at?->toISOString(),
            'lastScannedAt'     => $qr->last_scanned_at?->toISOString(),
            'isActive'          => true,
        ];
    }

    /** Derives status string from preloaded flip maps. */
    private function deriveStatus(int $tableId, array $activeFlip, array $unpaidFlip): string
    {
        if (isset($unpaidFlip[$tableId])) return 'waiting_payment'; // red
        if (isset($activeFlip[$tableId])) return 'active';          // yellow
        return 'idle';                                                   // green
    }

    private function getOrCreateTakeawayQr(Vendor $vendor): VendorTakeawayQr
    {
        return VendorTakeawayQr::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['qr_token'  => $this->generateTakeawayToken()]
        );
    }

    private function generateTakeawayToken(): string
    {
        do {
            $token = Str::uuid()->toString();
        } while (VendorTakeawayQr::where('qr_token', $token)->exists());

        return $token;
    }

    private function resolveVendor(string $vendorId): Vendor
    {
        return Vendor::where('vendor_public_id', $vendorId)
            ->when(ctype_digit($vendorId), fn ($q) => $q->orWhere('id', $vendorId))
            ->firstOrFail();
    }

    private function authorizeVendor(Request $request, Vendor $vendor): void
    {
        $user = $request->user();
        if ($user instanceof Vendor && $user->id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }

        if ($user instanceof TeamMember && $user->vendor_id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }
}
