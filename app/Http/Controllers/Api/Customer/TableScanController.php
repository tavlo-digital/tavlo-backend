<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TableScanController extends Controller
{
    /**
     * POST /api/customer/scan
     * Authenticated customer endpoint. Customer's app posts the QR token after scanning.
     *
        * Body: either
        *  - plain-text token string (Content-Type: text/plain)
        *  - JSON: { "token": "<qr_token>" } (backwards compatible)
     *
     * Behavior:
     *  - Resolve the table by qr_token.
     *  - Mark the table as scanned (last_scanned_at = now).
     *  - Create a new table_scan_session row with a unique 4-digit PIN
     *    linked to the authenticated customer.
     *
     * Returns: { pin, session: {...}, table: {...}, vendor: {...} }
     */
    public function scan(Request $request): JsonResponse
    {
        $token = $request->input('token');
        if ($token === null) {
            $token = trim((string) $request->getContent());
        }

        Validator::make(['token' => $token], [
            'token' => ['required', 'string'],
        ])->validate();

        $token = (string) $token;

        $table = RestaurantTable::with('vendor')
            ->where('qr_token', $token)
            ->first();

        if (! $table || ! $table->is_active) {
            return response()->json([
                'message' => 'This QR code is no longer valid',
            ], 410);
        }

        $vendor   = $table->vendor;
        $customer = $request->user();

        $result = DB::transaction(function () use ($table, $vendor, $customer) {
            $activeSession = TableScanSession::query()
                ->where('restaurant_table_id', $table->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($activeSession) {
                return ['existing' => $activeSession];
            }

            $table->update(['last_scanned_at' => now()]);

            $session = TableScanSession::create([
                'vendor_id'           => $vendor->id,
                'restaurant_table_id' => $table->id,
                'customer_id'         => $customer->id,
                'pin'                 => TableScanSession::generateUniquePin(),
                'status'              => 'active',
                'scanned_at'          => now(),
            ]);

            return ['created' => $session];
        });

        if (isset($result['existing'])) {
            return response()->json([
                'message' => 'This table already has an active session',
                'status'  => 'active',
            ], 409);
        }

        /** @var \App\Models\TableScanSession $session */
        $session = $result['created'];

        return response()->json([
            'pin'     => $session->pin,
            'session' => [
                'id'         => (string) $session->id,
                'status'     => $session->status,
                'scannedAt'  => $session->scanned_at?->toIso8601String(),
            ],
            'table' => [
                'id'     => (string) $table->id,
                'number' => $table->number,
                'name'   => $table->name,
            ],
            'vendor' => [
                'id'   => $vendor->vendor_public_id ?? (string) $vendor->id,
                'name' => $vendor->name,
            ],
        ], 201);
    }
}
