<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TableController extends Controller
{
    /**
     * GET /api/vendor/{vendorId}/tables
     */
    public function index(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        $tables = $vendor->restaurantTables()
            ->orderBy('number')
            ->get()
            ->map(fn (RestaurantTable $t) => $this->formatTable($t));

        return response()->json($tables);
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

        $vendor->restaurantTables()->findOrFail($tableId)->delete();

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
     * Called by customer-facing QR landing page to record a scan.
     */
    public function recordScan(string $vendorId, string $tableId): JsonResponse
    {
        $vendor = Vendor::where('vendor_public_id', $vendorId)
            ->orWhere('id', $vendorId)
            ->firstOrFail();

        $table = $vendor->restaurantTables()->findOrFail($tableId);
        $table->update(['last_scanned_at' => now()]);

        return response()->json(['message' => 'Scan recorded']);
    }

    /**
     * GET /api/vendor/{vendorId}/tables/takeaway-qr
     * Returns a virtual "takeaway" QR entry for the vendor.
     */
    public function takeawayQR(Request $request, string $vendorId): JsonResponse
    {
        $vendor = $this->resolveVendor($vendorId);
        $this->authorizeVendor($request, $vendor);

        // Use a stable derived token based on the vendor id so it survives restarts
        $token = hash('sha256', 'takeaway-' . $vendor->id . '-' . ($vendor->created_at ?? ''));

        return response()->json([
            'id'           => 'takeaway',
            'number'       => 0,
            'name'         => 'Takeaway',
            'qrToken'      => $token,
            'qrCreatedAt'  => $vendor->created_at?->toISOString(),
            'lastScannedAt' => null,
            'isActive'     => true,
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

        $desired = $request->validate(['count' => ['required', 'integer', 'min:0', 'max:500']]);
        $count = $desired['count'];

        $existing = $vendor->restaurantTables()->orderBy('number')->get();
        $currentCount = $existing->count();

        if ($count > $currentCount) {
            // Add missing tables
            for ($n = $currentCount + 1; $n <= $count; $n++) {
                $vendor->restaurantTables()->create([
                    'number'       => $n,
                    'name'         => "Table {$n}",
                    'qr_token'     => RestaurantTable::generateQrToken(),
                    'is_active'    => true,
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

    // ----------------------------------------------------------------

    private function formatTable(RestaurantTable $table): array
    {
        return [
            'id'           => (string) $table->id,
            'number'       => $table->number,
            'name'         => $table->name,
            'qrToken'      => $table->qr_token,
            'isActive'     => (bool) $table->is_active,
            'qrCreatedAt'  => $table->qr_created_at?->toISOString(),
            'lastScannedAt' => $table->last_scanned_at?->toISOString(),
        ];
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
        if ($user && $user->getTable() === 'vendors' && $user->id !== $vendor->id) {
            abort(403, 'Unauthorized');
        }
    }
}
