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
    * POST /api/customer/table/scan
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
        $token = $this->extractToken($request);
        $table = $this->findActiveTableByToken($token);

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
                ->where('pin', '!=', '')
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
            /** @var \App\Models\TableScanSession $existing */
            $existing = $result['existing'];

            return response()->json($this->sessionResponsePayload($existing, $table, $vendor, [
                'message'     => 'This table already has an active session',
                'status'      => 'active',
                'requiresPin' => true,
            ]), 409);
        }

        /** @var \App\Models\TableScanSession $session */
        $session = $result['created'];

        return response()->json($this->sessionResponsePayload($session, $table, $vendor, [
            'message'     => 'Table session started',
            'status'      => 'active',
            'requiresPin' => true,
        ]), 201);
    }

    /**
     * POST /api/customer/table/pin
     * Authenticated customer endpoint. Customer joins an already-active table session using the owner PIN.
     */
    public function pin(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'pin'   => ['required', 'string', 'size:4'],
        ])->validate();

        $table = $this->findActiveTableByToken($data['token']);

        if (! $table || ! $table->is_active) {
            return response()->json([
                'message' => 'This QR code is no longer valid',
            ], 410);
        }

        $vendor = $table->vendor;
        $customer = $request->user();

        $result = DB::transaction(function () use ($table, $vendor, $customer, $data) {
            $ownerSession = TableScanSession::query()
                ->where('restaurant_table_id', $table->id)
                ->where('status', 'active')
                ->where('pin', $data['pin'])
                ->lockForUpdate()
                ->first();

            if (! $ownerSession) {
                return ['invalid_pin' => true];
            }

            $existingCustomerSession = TableScanSession::query()
                ->where('restaurant_table_id', $table->id)
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->first();

            if ($existingCustomerSession) {
                return ['existing_customer_session' => $existingCustomerSession];
            }

            $table->update(['last_scanned_at' => now()]);

            $session = TableScanSession::create([
                'vendor_id'           => $vendor->id,
                'restaurant_table_id' => $table->id,
                'customer_id'         => $customer->id,
                'pin'                 => '',
                'status'              => 'active',
                'scanned_at'          => now(),
            ]);

            return ['created' => $session];
        });

        if (isset($result['invalid_pin'])) {
            return response()->json([
                'message' => 'The provided PIN is invalid for this table',
            ], 422);
        }

        if (isset($result['existing_customer_session'])) {
            /** @var \App\Models\TableScanSession $session */
            $session = $result['existing_customer_session'];

            return response()->json($this->sessionResponsePayload($session, $table, $vendor, [
                'message'     => 'Already joined this table session',
                'status'      => 'active',
                'requiresPin' => false,
            ]), 200);
        }

        /** @var \App\Models\TableScanSession $session */
        $session = $result['created'];

        return response()->json($this->sessionResponsePayload($session, $table, $vendor, [
            'message'     => 'Joined table session',
            'status'      => 'active',
            'requiresPin' => false,
        ]), 201);
    }

    private function extractToken(Request $request): string
    {
        $token = $request->input('token');

        if ($token === null) {
            $token = trim((string) $request->getContent());
        }

        Validator::make(['token' => $token], [
            'token' => ['required', 'string'],
        ])->validate();

        return (string) $token;
    }

    private function findActiveTableByToken(string $token): ?RestaurantTable
    {
        return RestaurantTable::with('vendor')
            ->where('qr_token', $token)
            ->first();
    }

    private function sessionResponsePayload(
        TableScanSession $session,
        RestaurantTable $table,
        mixed $vendor,
        array $extras = []
    ): array {
        $hasPin = $session->pin !== '';

        $base = [
            'message'     => $extras['message']     ?? 'Table session is active',
            'status'      => $extras['status']      ?? 'active',
            'requiresPin' => $extras['requiresPin'] ?? $hasPin,
            'pin'         => $hasPin ? $session->pin : null,
            'session'     => [
                'id'        => (string) $session->id,
                'status'    => $session->status,
                'scannedAt' => $session->scanned_at?->toIso8601String(),
            ],
            'table'  => $this->tablePayload($table),
            'vendor' => $this->vendorPayload($vendor),
        ];

        return $base;
    }

    private function tablePayload(RestaurantTable $table): array
    {
        return [
            'id'     => (string) $table->id,
            'number' => $table->number,
            'name'   => $table->name,
        ];
    }

    private function vendorPayload(mixed $vendor): array
    {
        return [
            'id'   => $vendor->vendor_public_id ?? (string) $vendor->id,
            'name' => $vendor->name,
        ];
    }
}
