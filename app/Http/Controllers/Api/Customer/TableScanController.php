<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\TeamMember;
use App\Models\Vendor;
use App\Models\VendorTakeawayQr;
use App\Services\NotificationService;
use App\Services\OrderSessionService;
use App\Services\VendorDateTimeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TableScanController extends Controller
{
    public function __construct(
        private readonly VendorDateTimeService $dateTimes,
        private readonly OrderSessionService $sessions,
    ) {}

    /**
     * GET /api/customer/table/status?token={qr_token}
     * Authenticated customer endpoint. Checks table availability before the customer starts/join a scan session.
     */
    public function status(Request $request): JsonResponse
    {
        if ($this->sessions->isOffPremise($this->sessions->mode($request))) {
            return $this->offPremiseStatus($request);
        }

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
            'table' => $this->tablePayload($table),
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
        if ($this->sessions->isOffPremise($this->sessions->mode($request))) {
            return $this->scanOffPremise($request);
        }

        $token = $this->extractToken($request);
        $table = $this->findActiveTableByToken($token);

        if (! $table || ! $table->is_active) {
            return response()->json([
                'message' => 'This QR code is no longer valid',
            ], 410);
        }

        $vendor = $table->vendor;
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
                'vendor_id' => $vendor->id,
                'restaurant_table_id' => $table->id,
                'customer_id' => $customer->id,
                'pin' => TableScanSession::generateUniquePin(),
                'status' => 'active',
                'scanned_at' => now(),
            ]);

            return ['created' => $session];
        });

        if (isset($result['customer_session'])) {
            /** @var TableScanSession $session */
            $session = $result['customer_session'];

            return response()->json($this->sessionResponsePayload($session, $table, $vendor, [
                'message' => 'Table session was already started',
                'status' => 'active',
                'requiresPin' => false,
            ]), 201);
        }

        if (isset($result['existing'])) {
            /** @var TableScanSession $existing */
            $existing = $result['existing'];

            return response()->json($this->sessionResponsePayload($existing, $table, $vendor, [
                'message' => 'This table already has an active session',
                'status' => 'active',
                'requiresPin' => true,
                'pin' => null,
            ]), 409);
        }

        /** @var TableScanSession $session */
        $session = $result['created'];

        return response()->json($this->sessionResponsePayload($session, $table, $vendor, [
            'message' => 'Table session started',
            'status' => 'active',
            'requiresPin' => false,
        ]), 201);
    }

    /**
     * POST /api/customer/table/pin
     * Authenticated customer endpoint. Customer joins an already-active table session using the owner PIN.
     */
    public function pin(Request $request): JsonResponse
    {
        if ($this->sessions->isOffPremise($this->sessions->mode($request))) {
            return $this->joinOffPremise($request);
        }

        $data = Validator::make($request->all(), [
            'token' => ['required', 'string'],
            'pin' => ['required', 'string', 'size:4'],
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
                'vendor_id' => $vendor->id,
                'restaurant_table_id' => $table->id,
                'customer_id' => $customer->id,
                'pin' => $data['pin'],
                'status' => 'active',
                'scanned_at' => now(),
            ]);

            return ['created' => $session];
        });

        if (isset($result['invalid_pin'])) {
            return response()->json([
                'message' => 'The provided PIN is invalid for this table',
            ], 422);
        }

        if (isset($result['existing_customer_session'])) {
            /** @var TableScanSession $session */
            $session = $result['existing_customer_session'];

            return response()->json($this->sessionResponsePayload($session, $table, $vendor, [
                'message' => 'Already joined this table session',
                'status' => 'active',
                'requiresPin' => false,
            ]), 200);
        }

        /** @var TableScanSession $session */
        $session = $result['created'];

        $customerName = trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'A guest';
        NotificationService::notifyTableCustomers(
            $table->id,
            'participant_added',
            "{$customerName} has joined the table.",
            [
                'template' => 'participant.added',
                'customer_id' => $customer->id,
                'customer_name' => $customerName,
                'table_id' => $table->id,
                'table_label' => $table->name ?? '#'.$table->number,
                'participant' => [
                    'session_id' => $session->id,
                    'customer_id' => $customer->id,
                    'name' => $customerName,
                    'scanned_at' => $this->dateTimes->formatDateTime($session->scanned_at, $vendor),
                    'status' => $session->status,
                ],
            ],
        );

        return response()->json($this->sessionResponsePayload($session, $table, $vendor, [
            'message' => 'Joined table session',
            'status' => 'active',
            'requiresPin' => false,
        ]), 201);
    }

    /**
     * GET /api/customer/table/session/status
     * Authenticated customer endpoint. Checks whether the customer still has an active table session.
     */
    public function sessionStatus(Request $request): JsonResponse
    {
        $session = $this->sessions->activeForCustomer((int) $request->user()->id, $request);
        $session?->load(['restaurantTable', 'vendor.vendorSetting']);

        if (! $session) {
            return response()->json([
                'active' => false,
                'session' => null,
                'table' => null,
                'vendor' => null,
            ]);
        }

        $table = $session->restaurantTable;
        $vendor = $session->vendor;

        return response()->json([
            'active' => true,
            'session' => [
                'id' => (string) $session->id,
                'status' => $session->status,
                'type' => $session->type,
                'pin' => $session->pin !== '' ? $session->pin : null,
                'scannedAt' => $this->dateTimes->formatDateTime($session->scanned_at, $vendor),
                'scheduledFor' => $this->dateTimes->formatDateTime($session->scheduled_for, $vendor),
            ],
            'table' => $table ? $this->tablePayload($table) : null,
            'vendor' => $vendor ? $this->vendorPayload($vendor) : null,
        ]);
    }

    /**
     * POST /api/customer/table/close
     * Authenticated customer endpoint. Closes the table scan session for all users.
     *
     * Blocks if any order is unpaid, or if paid orders have unserved items.
     */
    public function close(Request $request): JsonResponse
    {
        if ($this->sessions->isOffPremise($this->sessions->mode($request))) {
            return $this->closeOffPremise($request);
        }

        $data = Validator::make($request->all(), [
            'vendor_public_id' => ['sometimes', 'string'],
            'table_id' => ['required', 'integer'],
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

        $closedAt = now();

        TableScanSession::query()
            ->whereIn('id', $sessionIds)
            ->update([
                'status' => 'closed',
                'closed_at' => $closedAt,
            ]);

        NotificationService::notifyCustomers(
            $customerIds,
            'session_expire',
            'Your table session has been closed.',
            $allSessions->first()?->vendor_id,
            [
                'template' => 'session.closed',
                'table_id' => $data['table_id'],
            ],
        );

        $referenceSession = $customerSession ?? $allSessions->first();
        $table = $referenceSession->restaurantTable;
        $vendorLoaded = $table?->vendor;
        NotificationService::notifyOperations(
            (int) $referenceSession->vendor_id,
            'table_session_changed',
            'A table session was closed.',
            [NotificationService::VENDOR, NotificationService::WAITER, NotificationService::KITCHEN],
            [
                'resources' => ['orders', 'tables', 'dashboard', 'notifications'],
                'template' => 'staff.table_session_changed',
                'table_id' => $data['table_id'],
                'table_label' => $table?->name ?? $table?->number ?? $data['table_id'],
                'severity' => 'info',
                'sound' => null,
                'source_actor_type' => 'customer',
                'source_actor_id' => $request->user()?->id,
                'table_action' => 'closed',
            ],
        );

        return response()->json([
            'message' => 'Table session closed',
            'status' => 'closed',
            'session' => [
                'id' => (string) $referenceSession->id,
                'status' => 'closed',
                'scannedAt' => $this->dateTimes->formatDateTime($referenceSession->scanned_at, $vendorLoaded),
                'closedAt' => $this->dateTimes->formatDateTime($closedAt, $vendorLoaded),
            ],
            'table' => $table ? $this->tablePayload($table) : null,
            'vendor' => $vendorLoaded ? $this->vendorPayload($vendorLoaded) : null,
        ], 200);
    }

    private function offPremiseStatus(Request $request): JsonResponse
    {
        $data = Validator::make($request->query(), [
            'token' => ['nullable', 'string'],
            'vendor_public_id' => ['nullable', 'string'],
        ])->validate();

        $vendor = $this->resolveOffPremiseVendor($request, $data);
        if (! $vendor) {
            return response()->json(['message' => 'This ordering link is no longer valid.'], 410);
        }

        return response()->json([
            'table' => null,
            'vendor' => $this->statusVendorPayload($vendor),
            'status' => 'available',
            'orderMode' => $this->sessions->mode($request),
        ]);
    }

    private function scanOffPremise(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'token' => ['nullable', 'string'],
            'vendor_public_id' => ['nullable', 'string'],
            'scheduled_for' => ['nullable', 'date', 'after_or_equal:now'],
        ])->validate();

        $vendor = $this->resolveOffPremiseVendor($request, $data);
        if (! $vendor) {
            return response()->json(['message' => 'This ordering link is no longer valid.'], 410);
        }

        $mode = $this->sessions->mode($request);
        $customer = $request->user();

        // A vendor QR always represents an ASAP takeaway. Do not trust a stale
        // client-supplied schedule to change the semantics of the scanned QR.
        if ($mode === OrderSessionService::TAKEAWAY) {
            $data['scheduled_for'] = null;
        }

        $session = DB::transaction(function () use ($vendor, $mode, $customer, $data) {
            $existing = TableScanSession::query()
                ->where('vendor_id', $vendor->id)
                ->where('customer_id', $customer->id)
                ->where('type', $mode)
                ->where('status', 'active')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing) {
                if (array_key_exists('scheduled_for', $data)) {
                    $existing->update(['scheduled_for' => $data['scheduled_for']]);
                }

                return $existing->refresh();
            }

            return TableScanSession::create([
                'vendor_id' => $vendor->id,
                'restaurant_table_id' => null,
                'customer_id' => $customer->id,
                'type' => $mode,
                'pin' => TableScanSession::generateUniquePin(),
                'status' => 'active',
                'scanned_at' => now(),
                'scheduled_for' => $data['scheduled_for'] ?? null,
            ]);
        });

        return response()->json($this->offPremiseSessionPayload(
            $session,
            $vendor,
            'Order session started.',
        ), 201);
    }

    private function joinOffPremise(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'token' => ['nullable', 'string'],
            'vendor_public_id' => ['nullable', 'string'],
            'pin' => ['required', 'string', 'size:4'],
        ])->validate();

        $vendor = $this->resolveOffPremiseVendor($request, $data);
        if (! $vendor) {
            return response()->json(['message' => 'This ordering link is no longer valid.'], 410);
        }

        $mode = $this->sessions->mode($request);
        $customer = $request->user();

        $result = DB::transaction(function () use ($vendor, $mode, $customer, $data) {
            $ownerSession = TableScanSession::query()
                ->where('vendor_id', $vendor->id)
                ->where('type', $mode)
                ->where('status', 'active')
                ->where('pin', $data['pin'])
                ->lockForUpdate()
                ->oldest('id')
                ->first();

            if (! $ownerSession) {
                return ['invalid_pin' => true];
            }

            $existing = TableScanSession::query()
                ->where('vendor_id', $vendor->id)
                ->where('customer_id', $customer->id)
                ->where('type', $mode)
                ->where('status', 'active')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing) {
                $existing->update([
                    'pin' => $ownerSession->pin,
                    'scheduled_for' => $ownerSession->scheduled_for,
                ]);

                return ['session' => $existing->refresh(), 'created' => false];
            }

            $session = TableScanSession::create([
                'vendor_id' => $vendor->id,
                'restaurant_table_id' => null,
                'customer_id' => $customer->id,
                'type' => $mode,
                'pin' => $ownerSession->pin,
                'status' => 'active',
                'scanned_at' => now(),
                'scheduled_for' => $ownerSession->scheduled_for,
            ]);

            return ['session' => $session, 'created' => true];
        });

        if (isset($result['invalid_pin'])) {
            return response()->json([
                'message' => 'The provided PIN is invalid for this restaurant.',
            ], 422);
        }

        /** @var TableScanSession $session */
        $session = $result['session'];
        if ($result['created']) {
            $customerName = trim(($customer->first_name ?? '').' '.($customer->last_name ?? '')) ?: 'A guest';
            $this->sessions->notifyCustomers(
                $session,
                'participant_added',
                "{$customerName} joined the order.",
                [
                    'template' => 'participant.added',
                    'customer_id' => $customer->id,
                    'customer_name' => $customerName,
                    'participant' => [
                        'session_id' => $session->id,
                        'customer_id' => $customer->id,
                        'name' => $customerName,
                        'scanned_at' => $this->dateTimes->formatDateTime($session->scanned_at, $vendor),
                        'status' => $session->status,
                    ],
                ],
                false,
            );
        }

        return response()->json($this->offPremiseSessionPayload(
            $session,
            $vendor,
            $result['created'] ? 'Joined order session.' : 'Already joined order session.',
        ), $result['created'] ? 201 : 200);
    }

    private function closeOffPremise(Request $request): JsonResponse
    {
        $session = $this->sessions->activeForCustomer((int) $request->user()->id, $request);
        if (! $session) {
            return response()->json(['message' => 'No active order session found.'], 422);
        }

        $groupSessionIds = $this->sessions->groupSessionIds($session);
        $hasUnpaidOrders = Order::query()
            ->whereIn('table_scan_session_id', $groupSessionIds)
            ->where('status', '!=', Order::STATUS_CANCELLED)
            ->where('payment_received', false)
            ->exists();

        if ($hasUnpaidOrders) {
            return response()->json([
                'message' => 'This order group still has unpaid orders.',
            ], 422);
        }

        $customerIds = $this->sessions->groupCustomerIds($session);
        $closedAt = now();
        TableScanSession::query()->whereIn('id', $groupSessionIds)->update([
            'status' => 'closed',
            'closed_at' => $closedAt,
        ]);

        NotificationService::notifyCustomers(
            $customerIds,
            'session_expire',
            'Your order session has been closed.',
            (int) $session->vendor_id,
            ['template' => 'session.closed', 'order_mode' => $session->type],
        );

        return response()->json([
            'message' => 'Order session closed.',
            'status' => 'closed',
        ]);
    }

    private function resolveOffPremiseVendor(Request $request, array $data): ?Vendor
    {
        $mode = $this->sessions->mode($request);

        if ($mode === OrderSessionService::TAKEAWAY) {
            $token = trim((string) ($data['token'] ?? ''));
            if ($token === '') {
                return null;
            }

            $qr = VendorTakeawayQr::query()
                ->with('vendor.vendorSetting')
                ->where('qr_token', $token)
                ->first();
            $qr?->update(['last_scanned_at' => now()]);

            return $qr?->vendor;
        }

        $identifier = trim((string) ($data['vendor_public_id'] ?? ''));
        if ($identifier === '') {
            return null;
        }

        return Vendor::query()
            ->with('vendorSetting')
            ->where(function ($query) use ($identifier) {
                $query->where('vendor_public_id', $identifier)
                    ->orWhere('slug', $identifier);
            })
            ->first();
    }

    private function offPremiseSessionPayload(
        TableScanSession $session,
        Vendor $vendor,
        string $message,
    ): array {
        return [
            'message' => $message,
            'status' => 'active',
            'requiresPin' => false,
            'pin' => $session->pin,
            'session' => [
                'id' => (string) $session->id,
                'status' => $session->status,
                'type' => $session->type,
                'pin' => $session->pin,
                'scannedAt' => $this->dateTimes->formatDateTime($session->scanned_at, $vendor),
                'scheduledFor' => $this->dateTimes->formatDateTime($session->scheduled_for, $vendor),
            ],
            'table' => null,
            'vendor' => $this->vendorPayload($vendor),
        ];
    }

    /**
     * POST /api/customer/table/call
     * Public endpoint. Sends a notification to all waiters at the table's restaurant.
     */
    public function call(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'table_id' => ['required', 'integer', 'exists:restaurant_tables,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ])->validate();

        $table = RestaurantTable::query()->findOrFail($data['table_id']);
        $table->update(['call_waiter_at' => now()]);
        $tableLabel = $table->name ?? "#{$table->number}";
        $note = $data['note'] ?? null;
        $message = "Table {$tableLabel} is calling.";

        if ($note) {
            $message .= " Note: {$note}";
        }

        $waiterIds = TeamMember::query()
            ->where('vendor_id', $table->vendor_id)
            ->where('role', 'waiter')
            ->where('status', 'active')
            ->pluck('id');

        if ($waiterIds->isEmpty()) {
            return response()->json([
                'message' => 'No waiters available at this restaurant.',
            ], 422);
        }

        NotificationService::notifyOperations(
            $table->vendor_id,
            'table_call',
            $message,
            [NotificationService::WAITER],
            [
                'resources' => ['tables', 'notifications'],
                'template' => 'table.call',
                'table_id' => $table->id,
                'table_label' => $tableLabel,
                'note' => $note ? "Note: {$note}" : '',
                'severity' => 'urgent',
                'sound' => 'table_call',
                'source_actor_type' => 'customer',
                'source_actor_id' => null,
            ],
        );

        return response()->json([
            'message' => 'Waiters have been notified.',
        ]);
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
            'message' => $extras['message'] ?? 'Table session is active',
            'status' => $extras['status'] ?? 'active',
            'requiresPin' => $extras['requiresPin'] ?? $hasPin,
            'pin' => array_key_exists('pin', $extras) ? $extras['pin'] : ($hasPin ? $session->pin : null),
            'session' => [
                'id' => (string) $session->id,
                'status' => $session->status,
                'scannedAt' => $this->dateTimes->formatDateTime($session->scanned_at, $vendor),
            ],
            'table' => $this->tablePayload($table),
            'vendor' => $this->vendorPayload($vendor),
        ];

        return $base;
    }

    private function tablePayload(RestaurantTable $table): array
    {
        return [
            'id' => (string) $table->id,
            'number' => $table->number,
            'name' => $table->name,
        ];
    }

    private function vendorPayload(mixed $vendor): array
    {
        return [
            'id' => $vendor->vendor_public_id ?? (string) $vendor->id,
            'name' => $vendor->name,
            'slug' => $vendor->slug,
            'currency' => $vendor->currency,
            'logo_url' => $vendor->logo_url,
        ];
    }

    private function statusVendorPayload(mixed $vendor): array
    {
        return [
            'id' => $vendor->vendor_public_id ?? (string) $vendor->id,
            'name' => $vendor->name,
            'slug' => $vendor->slug,
        ];
    }
}
