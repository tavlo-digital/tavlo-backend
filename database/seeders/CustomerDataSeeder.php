<?php

namespace Database\Seeders;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\CustomerLoyaltyPoint;
use App\Models\GdprRequest;
use App\Models\LoyaltyTransaction;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\Refund;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Review;
use App\Models\ReviewItem;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Services\TaxCalculationService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Closed customer history: past visits with their scan session, cart items,
 * order, payment and review — the same object graph the live flow produces.
 *
 * TableSessionFlowSeeder covers the *open* table; everything here is finished
 * business, so orders end at `served` / `picked_up`.
 */
class CustomerDataSeeder extends Seeder
{
    private const SEEDED_CUSTOMER_PUBLIC_IDS = ['C-1024', 'C-2048', 'C-3072', 'C-4096', 'C-5120'];

    /** @var array<string, Customer> */
    private array $customers = [];

    /** @var array<string, Vendor> */
    private array $vendors = [];

    public function run(): void
    {
        $this->customers = Customer::whereIn('customer_public_id', self::SEEDED_CUSTOMER_PUBLIC_IDS)
            ->get()
            ->keyBy('customer_public_id')
            ->all();

        if (count($this->customers) < count(self::SEEDED_CUSTOMER_PUBLIC_IDS)) {
            $this->command->warn('CustomerDataSeeder: demo customers missing — run CustomerSeeder first.');

            return;
        }

        $this->vendors = Vendor::with('vendorSetting')
            ->whereIn('slug', ['bella-italia', 'burger-palace', 'sakura-sushi', 'green-bowl-cafe', 'pizza-express'])
            ->get()
            ->keyBy('slug')
            ->all();

        if (! isset($this->vendors['bella-italia'])) {
            $this->command->warn('CustomerDataSeeder: demo vendors missing — run VendorSeeder first.');

            return;
        }

        $this->purge();

        $orders = [];

        foreach ($this->visits() as $visit) {
            $order = $this->seedVisit($visit);

            if ($order) {
                $orders[$visit['ref']] = $order;
            }
        }

        $this->seedRefunds($orders);
        $this->seedActivities();
        $this->seedGdprRequests();
        $this->seedFavorites();
        $this->seedReservations();
        $this->refreshCustomerTotals();

        $this->command->info('CustomerDataSeeder: '.count($orders).' completed visits seeded.');
    }

    // ─── Visit definitions ──────────────────────────────────────────────────

    /**
     * Each visit becomes one scan session + cart + order (+ optional review).
     * `items` are [menu item name, quantity] pairs from the vendor's menu.
     *
     * @return array<int, array<string, mixed>>
     */
    private function visits(): array
    {
        return [
            [
                'ref' => 'anna-bella-1',
                'customer' => 'C-1024', 'vendor' => 'bella-italia', 'table' => 3,
                'days_ago' => 1, 'at' => '13:15', 'payment' => 'card',
                'items' => [['Spaghetti Carbonara', 1], ['Bruschetta al Pomodoro', 1], ['Fresh Lemonade', 2]],
                'review' => [
                    'rating' => 5,
                    'text' => 'Best carbonara in Vienna — creamy without being heavy. Service was quick even at lunch.',
                    'items' => ['Spaghetti Carbonara' => [5, 'Perfectly al dente.']],
                ],
            ],
            [
                'ref' => 'anna-sakura-1',
                'customer' => 'C-1024', 'vendor' => 'sakura-sushi', 'table' => 2,
                'days_ago' => 3, 'at' => '19:30', 'payment' => 'card',
                'items' => [],
                'amount_override' => 52.90,
                'review' => ['rating' => 4, 'text' => 'Good quality sushi, the omakase was worth it. Service a bit slow.'],
            ],
            [
                'ref' => 'anna-pizza-1',
                'customer' => 'C-1024', 'vendor' => 'pizza-express', 'table' => 4,
                'days_ago' => 5, 'at' => '18:45', 'payment' => 'card',
                'items' => [],
                'amount_override' => 28.00,
                'cancelled' => true,
                'review' => [
                    'rating' => 1,
                    'text' => 'Order never arrived but I was charged for it!',
                    'flagged' => true,
                    'flag_reason' => 'Potential abuse – aggressive language',
                ],
            ],
            [
                'ref' => 'anna-burger-1',
                'customer' => 'C-1024', 'vendor' => 'burger-palace', 'table' => 7,
                'days_ago' => 8, 'at' => '12:20', 'payment' => 'card',
                'items' => [['Classic Cheeseburger', 1], ['Hand-cut Fries', 1], ['Craft Cola', 1]],
                'review' => [
                    'rating' => 5,
                    'text' => 'Cooked exactly medium as asked. The hand-cut fries are the real star.',
                    'items' => ['Classic Cheeseburger' => [5, null], 'Hand-cut Fries' => [5, 'Crispy every time.']],
                ],
            ],
            [
                'ref' => 'anna-bella-2',
                'customer' => 'C-1024', 'vendor' => 'bella-italia', 'table' => 6,
                'days_ago' => 10, 'at' => '20:10', 'payment' => 'cash',
                'items' => [['Truffle Mushroom Risotto', 1], ['Aperol Spritz', 2], ['Tiramisu', 1]],
                'review' => ['rating' => 4, 'text' => 'Great risotto and generous portions. Slightly loud on a Saturday.'],
            ],
            [
                'ref' => 'max-bella-1',
                'customer' => 'C-2048', 'vendor' => 'bella-italia', 'table' => 3,
                'days_ago' => 2, 'at' => '12:00', 'payment' => 'card',
                'items' => [['Margherita Pizza', 1], ['San Pellegrino Sparkling Water', 1]],
                'review' => ['rating' => 5, 'text' => 'Consistently great pizza and fast service at lunch.'],
            ],
            [
                'ref' => 'max-burger-1',
                'customer' => 'C-2048', 'vendor' => 'burger-palace', 'table' => 11,
                'days_ago' => 5, 'at' => '19:45', 'payment' => 'card',
                'items' => [['Bacon BBQ Burger', 1], ['Crispy Onion Rings', 1], ['Vienna Lager 0.5 l', 2]],
            ],
            [
                'ref' => 'sophie-green-1',
                'customer' => 'C-3072', 'vendor' => 'green-bowl-cafe', 'table' => 5,
                'days_ago' => 15, 'at' => '11:30', 'payment' => 'card',
                'items' => [],
                'amount_override' => 150.00,
            ],
            [
                'ref' => 'sophie-bella-1',
                'customer' => 'C-3072', 'vendor' => 'bella-italia', 'table' => 9,
                'days_ago' => 20, 'at' => '14:00', 'payment' => 'card',
                'items' => [['Grilled Salmon Fillet', 2], ['Prosecco DOC', 4], ['Panna Cotta', 2]],
            ],
            [
                'ref' => 'guest-burger-1',
                'customer' => 'C-4096', 'vendor' => 'burger-palace',
                'pickup' => true,
                'days_ago' => 1, 'at' => '17:30', 'payment' => 'card',
                'items' => [['Garden Veggie Burger', 1], ['Vanilla Milkshake', 1]],
                'review' => ['rating' => 4, 'text' => 'Solid veggie burger, ready right on time for pickup.', 'anonymous' => true],
            ],
            [
                'ref' => 'thomas-bella-1',
                'customer' => 'C-5120', 'vendor' => 'bella-italia', 'table' => 8,
                'days_ago' => 3, 'at' => '12:00', 'payment' => 'card',
                'items' => [['Penne Arrabbiata', 2], ['Pizza Diavola', 1], ['Fresh Lemonade', 2]],
            ],
            [
                'ref' => 'thomas-burger-1',
                'customer' => 'C-5120', 'vendor' => 'burger-palace',
                'pickup' => true,
                'days_ago' => 7, 'at' => '18:30', 'payment' => 'cash',
                'items' => [['Classic Cheeseburger', 2], ['Hand-cut Fries', 2]],
            ],
        ];
    }

    // ─── Visit construction ─────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $visit
     */
    private function seedVisit(array $visit): ?Order
    {
        $customer = $this->customers[$visit['customer']];
        $vendor = $this->vendors[$visit['vendor']] ?? null;

        if (! $vendor) {
            return null;
        }

        $isPickup = (bool) ($visit['pickup'] ?? false);
        $table = $isPickup ? null : $this->table($vendor, $visit['table']);

        if (! $isPickup && ! $table) {
            $this->command->warn("CustomerDataSeeder: table {$visit['table']} missing for {$vendor->slug} — visit skipped.");

            return null;
        }

        [$hour, $minute] = array_map('intval', explode(':', $visit['at']));
        $scannedAt = now()->subDays($visit['days_ago'])->setTime($hour, $minute);

        $session = TableScanSession::create([
            'vendor_id' => $vendor->id,
            'restaurant_table_id' => $table?->id,
            'customer_id' => $customer->id,
            'type' => $isPickup ? 'pickup' : 'dine_in',
            // Pickup sessions carry no PIN — see PickupController::scan().
            'pin' => $isPickup ? '' : str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT),
            'status' => 'closed',
            'scanned_at' => $scannedAt,
            'closed_at' => $scannedAt->copy()->addMinutes(95),
            'created_at' => $scannedAt,
            'updated_at' => $scannedAt->copy()->addMinutes(95),
        ]);

        $order = Order::create([
            'order_public_id' => 'ord-'.Str::random(12),
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'table_scan_session_id' => $session->id,
            'status' => Order::STATUS_DRAFT,
            'amount' => 0,
            'currency' => $vendor->resolveCurrency(),
            'order_type' => $isPickup ? 'takeaway' : 'dine-in',
            'table_number' => $table?->number,
            'placed_by' => 'customer',
            'draft_at' => $scannedAt->copy()->addMinutes(4),
            'created_at' => $scannedAt->copy()->addMinutes(4),
            'updated_at' => $scannedAt->copy()->addMinutes(4),
        ]);

        $cartItems = $this->createCartItems($vendor, $session, $order, $visit['items'], $scannedAt);

        $totals = $this->totals($vendor, $cartItems, $visit['amount_override'] ?? null);

        $cancelled = (bool) ($visit['cancelled'] ?? false);
        $paidByCard = $visit['payment'] === 'card';

        $order->forceFill([
            'status' => $cancelled
                ? Order::STATUS_CANCELLED
                : ($isPickup ? Order::STATUS_PICKED_UP : Order::STATUS_SERVED),
            'amount' => $totals['total'],
            'service_fee' => $totals['service_fee'],
            'vat_amount' => $totals['vat'],
            'order_number' => $this->nextOrderNumber($vendor),
            'invoice_number' => $cancelled ? null : $this->nextInvoiceNumber($vendor),
            'payment_method' => $visit['payment'],
            'transaction_id' => $paidByCard ? 'pi_'.Str::random(24) : null,
            'payment_pending' => false,
            'payment_received' => ! $cancelled,
            'payment_confirmed_at' => $cancelled ? null : $scannedAt->copy()->addMinutes(90),
            'confirmed_at' => $scannedAt->copy()->addMinutes(6),
            'waiter_confirmed' => ! $isPickup,
            'waiter_confirmed_at' => $isPickup ? null : $scannedAt->copy()->addMinutes(8),
            'in_progress_at' => $cancelled ? null : $scannedAt->copy()->addMinutes(10),
            'served_at' => ($cancelled || $isPickup) ? null : $scannedAt->copy()->addMinutes(30),
            'picked_up_at' => ($cancelled || ! $isPickup) ? null : $scannedAt->copy()->addMinutes(25),
            'cancelled_at' => $cancelled ? $scannedAt->copy()->addMinutes(12) : null,
            'cancelled_reason' => $cancelled ? 'Customer reported the order never arrived' : null,
            'updated_at' => $scannedAt->copy()->addMinutes(95),
        ])->save();

        if ($paidByCard && ! $cancelled) {
            $this->createPayment($vendor, $customer, $session, $order, $totals['total'], $scannedAt);
        }

        if (! $cancelled) {
            $this->awardLoyaltyPoints($vendor, $customer, $order, $totals['total'], $scannedAt);
        }

        if (isset($visit['review'])) {
            $this->createReview($vendor, $customer, $session, $order, $cartItems, $visit['review'], $scannedAt);
        }

        return $order;
    }

    /**
     * @param  array<int, array{0: string, 1: int}>  $definitions
     * @return array<int, CartItem>
     */
    private function createCartItems(Vendor $vendor, TableScanSession $session, Order $order, array $definitions, CarbonInterface $scannedAt): array
    {
        $names = array_column($definitions, 0);

        $menuItems = MenuItem::where('vendor_id', $vendor->id)
            ->whereIn('name', $names)
            ->get()
            ->keyBy('name');

        $cartItems = [];

        foreach ($definitions as [$name, $quantity]) {
            $menuItem = $menuItems->get($name);

            if (! $menuItem) {
                $this->command->warn("CustomerDataSeeder: menu item [{$name}] not found for {$vendor->slug}.");

                continue;
            }

            $cartItems[] = CartItem::create([
                'table_scan_session_id' => $session->id,
                'menu_item_id' => $menuItem->id,
                'order_id' => $order->id,
                'client_item_id' => (string) Str::uuid(),
                'quantity' => $quantity,
                'received_at' => $scannedAt->copy()->addMinutes(6),
                'preparing_start_at' => $scannedAt->copy()->addMinutes(10),
                'ready_at' => $scannedAt->copy()->addMinutes(24),
                'served_at' => $scannedAt->copy()->addMinutes(28),
                'created_at' => $scannedAt->copy()->addMinutes(3),
                'updated_at' => $scannedAt->copy()->addMinutes(28),
            ]);
        }

        return $cartItems;
    }

    /**
     * Mirrors CartController: gross line totals, then the vendor's service fee.
     *
     * @param  array<int, CartItem>  $cartItems
     * @return array{items: float, service_fee: float, vat: float, total: float}
     */
    private function totals(Vendor $vendor, array $cartItems, ?float $override): array
    {
        $country = $vendor->country ?? 'AT';
        $itemsTotal = 0.0;
        $vatTotal = 0.0;

        foreach ($cartItems as $cartItem) {
            $cartItem->loadMissing('menuItem');
            $lineTotal = TaxCalculationService::cartItemLineTotalGross($cartItem, $country);
            $vatRate = TaxCalculationService::itemVatRate($cartItem->menuItem, $country);

            $itemsTotal += $lineTotal;
            $vatTotal += TaxCalculationService::vatFromGross($lineTotal, $vatRate);
        }

        // Visits at vendors without a seeded menu carry a flat historical total.
        if ($override !== null && $itemsTotal === 0.0) {
            $itemsTotal = $override;
            $vatTotal = TaxCalculationService::vatFromGross($override, 10.0);
        }

        $itemsTotal = round($itemsTotal, 2);
        $serviceFee = round($itemsTotal * ((float) ($vendor->vendorSetting?->service_fee_rate ?? 0) / 100), 2);

        return [
            'items' => $itemsTotal,
            'service_fee' => $serviceFee,
            'vat' => round($vatTotal, 2),
            'total' => round($itemsTotal + $serviceFee, 2),
        ];
    }

    private function createPayment(
        Vendor $vendor,
        Customer $customer,
        TableScanSession $session,
        Order $order,
        float $amount,
        CarbonInterface $scannedAt,
    ): void {
        OrderPayment::create([
            'order_id' => $order->id,
            'order_ids' => [$order->id],
            'vendor_id' => $vendor->id,
            'customer_id' => $customer->id,
            'table_scan_session_id' => $session->id,
            'stripe_account_id' => $vendor->vendorSetting?->stripe_account_id ?? 'acct_seed_'.$vendor->id,
            'stripe_payment_intent_id' => 'pi_'.Str::random(24),
            'amount' => $amount,
            'currency' => $order->currency,
            'status' => 'succeeded',
            'payment_method' => 'card',
            'payment_method_details' => ['brand' => 'visa', 'last4' => '4242'],
            'paid_at' => $scannedAt->copy()->addMinutes(90),
            'last_verified_at' => $scannedAt->copy()->addMinutes(90),
            'created_at' => $scannedAt->copy()->addMinutes(88),
            'updated_at' => $scannedAt->copy()->addMinutes(90),
        ]);
    }

    private function awardLoyaltyPoints(
        Vendor $vendor,
        Customer $customer,
        Order $order,
        float $amount,
        CarbonInterface $scannedAt,
    ): void {
        $settings = $vendor->vendorSetting;

        if (! $settings?->loyalty_enabled) {
            return;
        }

        $points = (int) floor($amount * (int) $settings->points_per_euro);

        if ($points <= 0) {
            return;
        }

        $balance = CustomerLoyaltyPoint::firstOrCreate(
            ['customer_id' => $customer->id, 'vendor_id' => $vendor->id],
            ['points_balance' => 0, 'total_earned' => 0, 'total_redeemed' => 0]
        );

        $balance->increment('points_balance', $points);
        $balance->increment('total_earned', $points);

        LoyaltyTransaction::create([
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'type' => 'earned',
            'points' => $points,
            'reference_type' => 'order',
            'reference_id' => $order->id,
            'description' => 'Points earned on order '.$order->order_public_id,
            'created_at' => $scannedAt->copy()->addMinutes(90),
            'updated_at' => $scannedAt->copy()->addMinutes(90),
        ]);
    }

    /**
     * @param  array<int, CartItem>  $cartItems
     * @param  array<string, mixed>  $definition
     */
    private function createReview(
        Vendor $vendor,
        Customer $customer,
        TableScanSession $session,
        Order $order,
        array $cartItems,
        array $definition,
        CarbonInterface $scannedAt,
    ): void {
        $reviewedAt = $scannedAt->copy()->addMinutes(100);

        $review = Review::create([
            'review_public_id' => 'REV-'.strtoupper(Str::random(8)),
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'table_scan_session_id' => $session->id,
            'rating' => $definition['rating'],
            'text' => $definition['text'],
            'anonymous' => (bool) ($definition['anonymous'] ?? false),
            'flagged' => (bool) ($definition['flagged'] ?? false),
            'flag_reason' => $definition['flag_reason'] ?? null,
            'vendor_reply' => $definition['reply'] ?? null,
            'vendor_replied_at' => isset($definition['reply']) ? $reviewedAt->copy()->addHours(6) : null,
            'created_at' => $reviewedAt,
            'updated_at' => $reviewedAt,
        ]);

        $itemRatings = $definition['items'] ?? [];

        foreach ($cartItems as $cartItem) {
            $name = $cartItem->menuItem?->name;

            if (! $name || ! isset($itemRatings[$name])) {
                continue;
            }

            [$rating, $text] = $itemRatings[$name];

            ReviewItem::create([
                'review_id' => $review->id,
                'cart_item_id' => $cartItem->id,
                'menu_item_id' => $cartItem->menu_item_id,
                'rating' => $rating,
                'text' => $text,
                'created_at' => $reviewedAt,
                'updated_at' => $reviewedAt,
            ]);
        }
    }

    // ─── Side data ──────────────────────────────────────────────────────────

    /**
     * @param  array<string, Order>  $orders
     */
    private function seedRefunds(array $orders): void
    {
        $refunds = [
            [
                'ref' => 'anna-pizza-1',
                'refund_public_id' => 'DIS-1024',
                'customer' => 'C-1024',
                'type' => 'dispute',
                'status' => 'open',
                'reason' => 'Card declined but still charged',
                'description' => 'Payment failed during checkout but the amount was deducted from the account.',
                'days_ago' => 4,
            ],
            [
                'ref' => 'anna-sakura-1',
                'refund_public_id' => 'REF-2048',
                'customer' => 'C-1024',
                'type' => 'refund',
                'status' => 'approved',
                'reason' => 'Food quality issue',
                'description' => 'Sushi was not fresh; the customer complained during the visit.',
                'days_ago' => 2,
                'resolved' => true,
            ],
            [
                'ref' => 'thomas-bella-1',
                'refund_public_id' => 'REF-3001',
                'customer' => 'C-5120',
                'type' => 'refund',
                'status' => 'pending',
                'reason' => 'Order arrived late and cold',
                'description' => 'Food took over 40 minutes to reach the table.',
                'days_ago' => 2,
            ],
            [
                'ref' => 'sophie-green-1',
                'refund_public_id' => 'DIS-3072',
                'customer' => 'C-3072',
                'type' => 'dispute',
                'status' => 'open',
                'reason' => 'Unauthorized charge',
                'description' => 'Customer claims they did not place this order.',
                'days_ago' => 10,
            ],
        ];

        foreach ($refunds as $refund) {
            $order = $orders[$refund['ref']] ?? null;

            if (! $order) {
                continue;
            }

            $createdAt = now()->subDays($refund['days_ago']);

            Refund::create([
                'refund_public_id' => $refund['refund_public_id'],
                'customer_id' => $this->customers[$refund['customer']]->id,
                'order_id' => $order->id,
                'type' => $refund['type'],
                'status' => $refund['status'],
                'amount' => $order->amount,
                'currency' => $order->currency,
                'reason' => $refund['reason'],
                'description' => $refund['description'],
                'resolved_at' => ($refund['resolved'] ?? false) ? $createdAt->copy()->addDays(2) : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function seedActivities(): void
    {
        $activities = [
            'C-1024' => [
                ['login', 'Login', 'Logged in via mobile app', 'iPhone 15', 1, '14:32'],
                ['order_placed', 'Order Placed', 'Confirmed an order at Bella Italia', 'iPhone 15', 1, '13:21'],
                ['qr_scan', 'QR Scan', 'Scanned the QR code at table 3', 'iPhone 15', 1, '13:15'],
                ['review_submitted', 'Review Submitted', 'Left a 5-star review for Bella Italia', 'iPhone 15', 1, '14:55'],
                ['dispute_filed', 'Dispute Filed', 'Dispute DIS-1024 opened', 'Chrome 120', 4, '10:45'],
                ['order_cancelled', 'Order Cancelled', 'Order at Pizza Express cancelled', 'Chrome 120', 5, '18:57'],
                ['qr_scan', 'QR Scan', 'Scanned the QR code at table 7', 'iPhone 15', 8, '12:20'],
                ['login', 'Login', 'Logged in via mobile app', 'iPhone 15', 10, '20:05'],
            ],
            'C-2048' => [
                ['qr_scan', 'QR Scan', 'Scanned the QR code at table 3', 'Pixel 8', 2, '12:00'],
                ['order_placed', 'Order Placed', 'Confirmed an order at Bella Italia', 'Pixel 8', 2, '12:06'],
                ['login', 'Login', 'Logged in via web browser', 'Chrome 120', 5, '19:40'],
            ],
            'C-3072' => [
                ['dispute_filed', 'Dispute Filed', 'Dispute DIS-3072 opened', 'Safari 17', 10, '09:00'],
                ['login', 'Login', 'Logged in via web browser', 'Safari 17', 10, '08:55'],
                ['order_placed', 'Order Placed', 'Confirmed an order at Bella Italia', 'Safari 17', 20, '14:06'],
            ],
            'C-4096' => [
                ['qr_scan', 'QR Scan', 'Scanned the takeaway QR at Burger Palace', 'Android 14', 1, '17:30'],
                ['order_placed', 'Order Placed', 'Confirmed a pickup order', 'Android 14', 1, '17:36'],
            ],
            'C-5120' => [
                ['refund_requested', 'Refund Requested', 'Refund REF-3001 requested', 'Android 14', 2, '14:00'],
                ['order_placed', 'Order Placed', 'Confirmed an order at Bella Italia', 'Android 14', 3, '12:06'],
                ['login', 'Login', 'Logged in via mobile app', 'Android 14', 3, '11:55'],
            ],
        ];

        $ipByCustomer = [
            'C-1024' => '185.34.12.87',
            'C-2048' => '91.114.55.12',
            'C-3072' => '91.12.203.4',
            'C-4096' => '78.45.19.201',
            'C-5120' => '78.45.88.34',
        ];

        foreach ($activities as $publicId => $rows) {
            foreach ($rows as [$eventType, $title, $detail, $device, $daysAgo, $at]) {
                [$hour, $minute] = array_map('intval', explode(':', $at));
                $createdAt = now()->subDays($daysAgo)->setTime($hour, $minute);

                CustomerActivity::create([
                    'customer_id' => $this->customers[$publicId]->id,
                    'event_type' => $eventType,
                    'title' => $title,
                    'detail' => $detail,
                    'ip_address' => $ipByCustomer[$publicId],
                    'device' => $device,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }

    private function seedGdprRequests(): void
    {
        $requests = [
            ['customer' => 'C-3072', 'type' => 'data_export', 'status' => 'completed', 'reason' => 'Customer requested a full data export.', 'days_ago' => 8, 'completed_days_ago' => 5],
            ['customer' => 'C-3072', 'type' => 'account_deletion', 'status' => 'pending', 'reason' => 'Customer wants the account removed after the dispute.', 'days_ago' => 2],
            ['customer' => 'C-5120', 'type' => 'data_export', 'status' => 'pending', 'reason' => 'Requested a copy of all personal data.', 'days_ago' => 1],
        ];

        foreach ($requests as $request) {
            $createdAt = now()->subDays($request['days_ago']);

            GdprRequest::create([
                'customer_id' => $this->customers[$request['customer']]->id,
                'type' => $request['type'],
                'status' => $request['status'],
                'reason' => $request['reason'],
                'completed_at' => isset($request['completed_days_ago'])
                    ? now()->subDays($request['completed_days_ago'])
                    : null,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }

    private function seedFavorites(): void
    {
        $favorites = [
            'C-1024' => ['bella-italia', 'burger-palace'],
            'C-2048' => ['bella-italia'],
            'C-5120' => ['burger-palace'],
        ];

        foreach ($favorites as $publicId => $vendorSlugs) {
            $vendorIds = collect($vendorSlugs)
                ->map(fn (string $slug) => $this->vendors[$slug]->id ?? null)
                ->filter()
                ->all();

            $this->customers[$publicId]->favorites()->sync($vendorIds);
        }
    }

    private function seedReservations(): void
    {
        $reservations = [
            ['customer' => 'C-1024', 'vendor' => 'bella-italia', 'in_days' => 2, 'time' => '19:00', 'party' => 4, 'status' => 'confirmed', 'table' => '6'],
            ['customer' => 'C-2048', 'vendor' => 'bella-italia', 'in_days' => 5, 'time' => '20:30', 'party' => 2, 'status' => 'pending', 'table' => null],
            ['customer' => 'C-5120', 'vendor' => 'burger-palace', 'in_days' => 1, 'time' => '18:00', 'party' => 6, 'status' => 'confirmed', 'table' => '11'],
        ];

        foreach ($reservations as $reservation) {
            $vendor = $this->vendors[$reservation['vendor']] ?? null;

            if (! $vendor) {
                continue;
            }

            $customer = $this->customers[$reservation['customer']];

            Reservation::create([
                'reservation_public_id' => 'RES-'.strtoupper(Str::random(8)),
                'vendor_id' => $vendor->id,
                'customer_id' => $customer->id,
                'guest_name' => trim($customer->first_name.' '.$customer->last_name),
                'guest_email' => $customer->email,
                'guest_phone' => $customer->phone,
                'date' => now()->addDays($reservation['in_days'])->toDateString(),
                'time' => $reservation['time'],
                'party_size' => $reservation['party'],
                'status' => $reservation['status'],
                'table_number' => $reservation['table'],
            ]);
        }
    }

    /**
     * Keep the denormalised counters on `customers` in step with the rows above.
     */
    private function refreshCustomerTotals(): void
    {
        foreach ($this->customers as $customer) {
            $completed = Order::where('customer_id', $customer->id)
                ->whereNull('cancelled_at')
                ->get(['amount']);

            $customer->forceFill([
                'orders_count' => $completed->count(),
                'total_spend' => round((float) $completed->sum('amount'), 2),
                'loyalty_points' => (int) LoyaltyTransaction::where('customer_id', $customer->id)
                    ->where('type', 'earned')
                    ->sum('points'),
            ])->save();
        }
    }

    // ─── Helpers ────────────────────────────────────────────────────────────

    private function table(Vendor $vendor, int $number): ?RestaurantTable
    {
        return RestaurantTable::where('vendor_id', $vendor->id)
            ->where('number', $number)
            ->first();
    }

    private function nextOrderNumber(Vendor $vendor): int
    {
        return (int) (Order::where('vendor_id', $vendor->id)->max('order_number') ?? 9000) + 1;
    }

    /**
     * Same format and counter the receipt endpoints use.
     */
    private function nextInvoiceNumber(Vendor $vendor): string
    {
        $settings = DB::table('vendor_settings')->where('vendor_id', $vendor->id);
        $next = (int) ($settings->value('next_invoice_number') ?? 1001);
        $prefix = $settings->value('invoice_prefix') ?? 'INV';

        $settings->increment('next_invoice_number');

        return $prefix.'-'.str_pad((string) $next, 7, '0', STR_PAD_LEFT);
    }

    /**
     * Remove everything this seeder owns for the demo customers so it can be
     * re-run. Cart items, review items and session activity cascade away with
     * their parents; orders and reviews only null out, so they go explicitly.
     */
    private function purge(): void
    {
        $customerIds = collect($this->customers)->pluck('id')->all();

        DB::table('review_items')
            ->whereIn('review_id', DB::table('reviews')->whereIn('customer_id', $customerIds)->select('id'))
            ->delete();

        DB::table('reviews')->whereIn('customer_id', $customerIds)->delete();
        DB::table('refunds')->whereIn('customer_id', $customerIds)->delete();
        DB::table('order_payments')->whereIn('customer_id', $customerIds)->delete();
        DB::table('cart_items')
            ->whereIn('order_id', DB::table('orders')->whereIn('customer_id', $customerIds)->select('id'))
            ->delete();
        DB::table('orders')->whereIn('customer_id', $customerIds)->delete();
        DB::table('table_scan_sessions')->whereIn('customer_id', $customerIds)->delete();
        DB::table('customer_activities')->whereIn('customer_id', $customerIds)->delete();
        DB::table('gdpr_requests')->whereIn('customer_id', $customerIds)->delete();
        DB::table('loyalty_transactions')->whereIn('customer_id', $customerIds)->delete();
        DB::table('customer_loyalty_points')->whereIn('customer_id', $customerIds)->delete();
        DB::table('customer_favorites')->whereIn('customer_id', $customerIds)->delete();
        DB::table('reservations')->whereIn('customer_id', $customerIds)->delete();
    }
}
