<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TableScanSession;
use App\Models\VendorTakeawayQr;
use App\Services\VendorDateTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PickupController extends Controller
{
    public function __construct(private readonly VendorDateTimeService $dateTimes) {}

    /**
     * POST /api/customer/pickup/scan
     *
     * Customer scans the vendor's takeaway QR code.
     * Creates a pickup-type session (no table, no PIN).
     */
    public function scan(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'token' => ['required', 'string'],
        ])->validate();

        $takeawayQr = VendorTakeawayQr::where('qr_token', $data['token'])->first();

        if (! $takeawayQr) {
            return response()->json([
                'message' => 'This QR code is no longer valid.',
            ], 410);
        }

        $takeawayQr->update(['last_scanned_at' => now()]);

        $vendor = $takeawayQr->vendor()->with('vendorSetting')->first();
        $customer = $request->user();

        $session = DB::transaction(function () use ($vendor, $customer) {
            $existing = TableScanSession::query()
                ->where('vendor_id', $vendor->id)
                ->where('customer_id', $customer->id)
                ->where('type', 'pickup')
                ->where('status', 'active')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            return TableScanSession::create([
                'vendor_id' => $vendor->id,
                'restaurant_table_id' => null,
                'customer_id' => $customer->id,
                'type' => 'pickup',
                'pin' => '',
                'status' => 'active',
                'scanned_at' => now(),
            ]);
        });

        return response()->json([
            'message' => 'Pickup session started.',
            'session' => [
                'id' => (string) $session->id,
                'status' => $session->status,
                'scannedAt' => $this->dateTimes->formatDateTime($session->scanned_at, $vendor),
            ],
            'vendor' => [
                'id' => $vendor->vendor_public_id,
                'name' => $vendor->name,
                'slug' => $vendor->slug,
                'currency' => $vendor->currency,
                'logo_url' => $vendor->logo_url,
            ],
        ], 201);
    }

    /**
     * GET /api/customer/pickup/session/status
     *
     * Checks whether the customer has an active pickup session.
     */
    public function sessionStatus(Request $request): JsonResponse
    {
        $session = TableScanSession::query()
            ->with(['vendor.vendorSetting'])
            ->where('customer_id', $request->user()->id)
            ->where('type', 'pickup')
            ->where('status', 'active')
            ->latest('scanned_at')
            ->latest('id')
            ->first();

        if (! $session) {
            return response()->json([
                'active' => false,
                'session' => null,
                'vendor' => null,
            ]);
        }

        $vendor = $session->vendor;

        return response()->json([
            'active' => true,
            'session' => [
                'id' => (string) $session->id,
                'status' => $session->status,
                'scannedAt' => $this->dateTimes->formatDateTime($session->scanned_at, $vendor),
            ],
            'vendor' => [
                'id' => $vendor->vendor_public_id,
                'name' => $vendor->name,
                'slug' => $vendor->slug,
                'currency' => $vendor->currency,
                'logo_url' => $vendor->logo_url,
            ],
        ]);
    }

    /**
     * POST /api/customer/pickup/close
     *
     * Closes the customer's active pickup session.
     * Blocks if there are unpaid orders.
     */
    public function close(Request $request): JsonResponse
    {
        $customer = $request->user();

        $session = TableScanSession::query()
            ->where('customer_id', $customer->id)
            ->where('type', 'pickup')
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (! $session) {
            return response()->json([
                'message' => 'No active pickup session found.',
            ], 422);
        }

        $unpaidOrders = Order::query()
            ->where('table_scan_session_id', $session->id)
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->where('payment_received', false)
            ->exists();

        if ($unpaidOrders) {
            return response()->json([
                'message' => 'You have unpaid orders. Please complete payment first.',
            ], 422);
        }

        $session->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Pickup session closed.',
            'status' => 'closed',
        ]);
    }

    /**
     * GET /api/customer/pickup/status
     *
     * Public endpoint. Resolves a takeaway QR token to vendor info.
     * Used by the customer app redirect page to find the vendor slug.
     */
    public function tokenStatus(Request $request): JsonResponse
    {
        $data = Validator::make($request->query(), [
            'token' => ['required', 'string'],
        ])->validate();

        $takeawayQr = VendorTakeawayQr::where('qr_token', $data['token'])->first();

        if (! $takeawayQr) {
            return response()->json([
                'message' => 'This QR code is no longer valid.',
            ], 410);
        }

        $vendor = $takeawayQr->vendor;

        return response()->json([
            'vendor' => [
                'id' => $vendor->vendor_public_id,
                'name' => $vendor->name,
                'slug' => $vendor->slug,
            ],
            'type' => 'pickup',
        ]);
    }
}
