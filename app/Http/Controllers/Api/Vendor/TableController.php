<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use App\Models\VendorTakeawayQr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
            ->whereIn('status', ['pending', 'confirmed', 'preparing', 'ready'])
            ->exists();

        if ($hasActiveOrders) {
            return response()->json(['message' => 'Cannot delete table with active orders'], 409);
        }

        $table->delete();

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

        $vendor->restaurantTables()->each(function (RestaurantTable $table) {
            $table->refreshQr();
        });

        $tables = $vendor->restaurantTables()
            ->orderBy('number')
            ->get()
            ->map(fn (RestaurantTable $t) => $this->formatTable($t));

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

        $sessions = TableScanSession::where('vendor_id', $vendor->id)
            ->where('restaurant_table_id', $table->id)
            ->where('status', 'active')
            ->get();

        if ($sessions->isEmpty()) {
            return response()->json(['message' => 'No active table session found.'], 404);
        }

        $orders = Order::whereIn('table_scan_session_id', $sessions->pluck('id'))
            ->where('status', '!=', 'cancelled')
            ->get();

        $total = round((float) $orders->sum(fn (Order $order) => (float) $order->amount), 2);
        $paid = round((float) $orders
            ->filter(fn (Order $order) => (bool) $order->payment_received)
            ->sum(fn (Order $order) => (float) $order->amount), 2);
        $remaining = round(max(0, $total - $paid), 2);
        $cashPending = $orders
            ->filter(fn (Order $order) => $order->payment_method === 'cash'
                && (bool) $order->payment_pending
                && ! (bool) $order->payment_received)
            ->count();

        if ($remaining > 0 && ! ($data['force'] ?? false)) {
            return response()->json([
                'message' => 'This table still has unpaid balances.',
                'paymentSummary' => [
                    'totalAmount'       => $total,
                    'paidAmount'        => $paid,
                    'remainingAmount'   => $remaining,
                    'cashPendingOrders' => $cashPending,
                    'ordersCount'       => $orders->count(),
                ],
            ], 409);
        }

        TableScanSession::whereIn('id', $sessions->pluck('id'))->update([
            'status'    => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Table session closed',
            'table' => $this->formatTable($table->fresh(), 'idle'),
            'closedSessionIds' => $sessions->pluck('id')->map(fn ($id) => (string) $id)->values(),
            'paymentSummary' => [
                'totalAmount'       => $total,
                'paidAmount'        => $paid,
                'remainingAmount'   => $remaining,
                'cashPendingOrders' => $cashPending,
                'ordersCount'       => $orders->count(),
            ],
        ]);
    }

    // ----------------------------------------------------------------
    // Private helpers
    // ----------------------------------------------------------------

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
            ->orWhere('id', $vendorId)
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
