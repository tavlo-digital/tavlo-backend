<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\CustomerSessionActivity;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TableScanController extends Controller
{
    /**
     * GET /api/customer/table/status?token={qr_token}
     * Authenticated customer endpoint. Checks table availability before the customer starts/join a scan session.
     */
    public function status(Request $request): JsonResponse
    {
        $data = Validator::make($request->query(), [
            'token' => ['required', 'string'],
        ])->validate();

        $token = (string) $data['token'];
        $table = $this->findActiveTableByToken($token);

        if (! $table || ! $table->is_active) {
            return response()->json([
                'message' => 'This QR code is no longer valid',
            ], 410);
        }

        $activeSessionIds = TableScanSession::query()
            ->where('restaurant_table_id', $table->id)
            ->where('status', 'active')
            ->pluck('id');

        $status = 'available';

        if ($activeSessionIds->isNotEmpty()) {
            $status = CartItem::query()
                ->whereIn('table_scan_session_id', $activeSessionIds)
                ->exists()
                    ? 'active'
                    : 'draft';
        }

        return response()->json([
            'table'  => $this->tablePayload($table),
            'vendor' => $this->statusVendorPayload($table->vendor),
            'status' => $status,
        ]);
    }

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
            $customerSession = TableScanSession::query()
                ->where('restaurant_table_id', $table->id)
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->where('pin', '!=', '')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($customerSession) {
                return ['customer_session' => $customerSession];
            }

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

        if (isset($result['customer_session'])) {
            /** @var \App\Models\TableScanSession $session */
            $session = $result['customer_session'];

            return response()->json($this->sessionResponsePayload($session, $table, $vendor, [
                'message'     => 'Table session was already started',
                'status'      => 'active',
                'requiresPin' => false,
            ]), 201);
        }

        if (isset($result['existing'])) {
            /** @var \App\Models\TableScanSession $existing */
            $existing = $result['existing'];

            return response()->json($this->sessionResponsePayload($existing, $table, $vendor, [
                'message'     => 'This table already has an active session',
                'status'      => 'active',
                'requiresPin' => true,
                'pin'         => null,
            ]), 409);
        }

        /** @var \App\Models\TableScanSession $session */
        $session = $result['created'];

        return response()->json($this->sessionResponsePayload($session, $table, $vendor, [
            'message'     => 'Table session started',
            'status'      => 'active',
            'requiresPin' => false,
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
                if ($existingCustomerSession->pin !== $data['pin']) {
                    $existingCustomerSession->update(['pin' => $data['pin']]);
                    $existingCustomerSession->refresh();
                }

                return ['existing_customer_session' => $existingCustomerSession];
            }

            $table->update(['last_scanned_at' => now()]);

            $session = TableScanSession::create([
                'vendor_id'           => $vendor->id,
                'restaurant_table_id' => $table->id,
                'customer_id'         => $customer->id,
                'pin'                 => $data['pin'],
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

        $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: 'A guest';
        NotificationService::notifyTableCustomers($table->id, 'participant_added', "{$customerName} has joined the table.");

        return response()->json($this->sessionResponsePayload($session, $table, $vendor, [
            'message'     => 'Joined table session',
            'status'      => 'active',
            'requiresPin' => false,
        ]), 201);
    }

    /**
     * POST /api/customer/table/close
     * Authenticated customer endpoint. Closes the table scan session for all users.
     *
     * Session user: closes if no session user has an active order. No activity check.
     * Non-session user: closes only if no activity for 10 minutes AND no active orders.
     */
    public function close(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'vendor_public_id' => ['sometimes', 'string'],
            'table_id'         => ['required', 'integer'],
        ])->validate();

        $customer = $request->user();

        $allSessions = TableScanSession::query()
            ->with(['restaurantTable.vendor'])
            ->where('restaurant_table_id', $data['table_id'])
            ->where('status', 'active')
            ->when(isset($data['vendor_public_id']), function ($query) use ($data) {
                $query->whereHas('vendor', function ($vendorQuery) use ($data) {
                    $vendorQuery->where('vendor_public_id', $data['vendor_public_id']);
                });
            })
            ->get();

        if ($allSessions->isEmpty()) {
            return response()->json([
                'message' => 'No active table session found.',
            ], 422);
        }

        $customerSession = $allSessions->firstWhere('customer_id', $customer->id);
        $isSessionUser = $customerSession !== null;

        if (! $isSessionUser) {
            $hasRecentActivity = CustomerSessionActivity::query()
                ->whereIn('table_scan_session_id', $allSessions->pluck('id'))
                ->where('created_at', '>=', now()->subMinutes(10))
                ->exists();

            if ($hasRecentActivity) {
                return response()->json([
                    'message' => 'Session is still active. Please wait until there is no activity for 10 minutes.',
                ], 422);
            }
        }

        $sessionIds = $allSessions->pluck('id');

        $orders = Order::query()
            ->whereIn('table_scan_session_id', $sessionIds)
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->get();

        if ($orders->contains(fn (Order $order) => ! $order->payment_received)) {
            return response()->json([
                'message' => 'There is an active order on this table.',
            ], 422);
        }

        $paidOrderIds = $orders
            ->where('payment_received', true)
            ->pluck('id')
            ->values();

        if ($paidOrderIds->isNotEmpty() && $this->hasUnservedCartItemsForOrders($paidOrderIds->all())) {
            return response()->json([
                'message' => 'All the items on table are not served.',
            ], 422);
        }

        $customerIds = $allSessions->pluck('customer_id')->filter()->unique();

        TableScanSession::query()
            ->whereIn('id', $sessionIds)
            ->update([
                'status'    => 'closed',
                'closed_at' => now(),
            ]);

        NotificationService::notifyCustomers($customerIds, 'session_expire', 'Your table session has been closed.');

        $referenceSession = $customerSession ?? $allSessions->first();
        $table = $referenceSession->restaurantTable;
        $vendorLoaded = $table?->vendor;

        return response()->json([
            'message' => 'Table session closed',
            'status'  => 'closed',
            'session' => [
                'id'        => (string) $referenceSession->id,
                'status'    => 'closed',
                'scannedAt' => $referenceSession->scanned_at?->toIso8601String(),
                'closedAt'  => now()->toIso8601String(),
            ],
            'table'  => $table        ? $this->tablePayload($table)         : null,
            'vendor' => $vendorLoaded ? $this->vendorPayload($vendorLoaded) : null,
        ], 200);
    }

    private function hasUnservedCartItemsForOrders(array $orderIds): bool
    {
        return CartItem::query()
            ->where(function ($query) use ($orderIds) {
                $query->whereIn('order_id', $orderIds);

                foreach ($orderIds as $orderId) {
                    $query->orWhereJsonContains('shared_order_ids', $orderId);
                }
            })
            ->whereNull('served_at')
            ->exists();
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
        return RestaurantTable::with('vendor.vendorSetting')
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
            'pin'         => array_key_exists('pin', $extras) ? $extras['pin'] : ($hasPin ? $session->pin : null),
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
            'id'       => $vendor->vendor_public_id ?? (string) $vendor->id,
            'name'     => $vendor->name,
            'currency' => $vendor->vendorSetting?->currency ?? 'EUR',
        ];
    }

    private function statusVendorPayload(mixed $vendor): array
    {
        return [
            'id'   => $vendor->vendor_public_id ?? (string) $vendor->id,
            'name' => $vendor->name,
        ];
    }
}
