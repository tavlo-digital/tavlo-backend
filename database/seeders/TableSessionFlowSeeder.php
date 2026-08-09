<?php

namespace Database\Seeders;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Services\TaxCalculationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * One *live* table: three guests sharing a table at Bella Italia with
 * confirmed-but-unpaid orders and two split items.
 *
 * Gives the vendor dashboard, KDS and waiter views something to work on
 * without having to walk the customer flow by hand.
 */
class TableSessionFlowSeeder extends Seeder
{
    private const VENDOR_SLUG = 'bella-italia';

    private const TABLE_NUMBER = 1;

    private const TABLE_PIN = '1234';

    public function run(): void
    {
        $vendor = Vendor::with('vendorSetting')->where('slug', self::VENDOR_SLUG)->first();

        if (! $vendor) {
            $this->command->warn('TableSessionFlowSeeder: '.self::VENDOR_SLUG.' not found — run VendorSeeder first.');

            return;
        }

        $table = RestaurantTable::where('vendor_id', $vendor->id)
            ->where('number', self::TABLE_NUMBER)
            ->first();

        if (! $table) {
            $this->command->warn('TableSessionFlowSeeder: table '.self::TABLE_NUMBER.' not found — run VendorSeeder first.');

            return;
        }

        $guests = [
            'anna' => Customer::where('customer_public_id', 'C-1024')->first(),
            'max' => Customer::where('customer_public_id', 'C-2048')->first(),
            'guest' => Customer::where('customer_public_id', 'C-4096')->first(),
        ];

        if (in_array(null, $guests, true)) {
            $this->command->warn('TableSessionFlowSeeder: demo customers missing — run CustomerSeeder first.');

            return;
        }

        $menu = MenuItem::where('vendor_id', $vendor->id)
            ->whereIn('name', [
                'Spaghetti Carbonara',
                'Bruschetta al Pomodoro',
                'Prosecco DOC',
                'Margherita Pizza',
                'Fresh Lemonade',
                'Tiramisu',
                'Penne Arrabbiata',
                'Aperol Spritz',
            ])
            ->get()
            ->keyBy('name');

        if ($menu->count() < 8) {
            $this->command->warn('TableSessionFlowSeeder: Bella Italia menu incomplete — run MenuSeeder first.');

            return;
        }

        $this->clearOpenSessions($table);

        // ── Anna scans the QR, the others join with her PIN ──────────────────

        $sessions = [
            'anna' => $this->openSession($vendor, $table, $guests['anna'], 30),
            'max' => $this->openSession($vendor, $table, $guests['max'], 28),
            'guest' => $this->openSession($vendor, $table, $guests['guest'], 25),
        ];

        $table->update(['last_scanned_at' => now()->subMinutes(25)]);

        // ── Everyone builds their own basket ─────────────────────────────────

        $carts = [
            'anna' => [
                'carbonara' => $this->addToCart($sessions['anna'], $menu['Spaghetti Carbonara'], 1, 22),
                'bruschetta' => $this->addToCart($sessions['anna'], $menu['Bruschetta al Pomodoro'], 1, 22),
                'prosecco' => $this->addToCart($sessions['anna'], $menu['Prosecco DOC'], 1, 21),
            ],
            'max' => [
                'pizza' => $this->addToCart($sessions['max'], $menu['Margherita Pizza'], 1, 20),
                'lemonade' => $this->addToCart($sessions['max'], $menu['Fresh Lemonade'], 2, 20),
                'tiramisu' => $this->addToCart($sessions['max'], $menu['Tiramisu'], 1, 18),
            ],
            'guest' => [
                'penne' => $this->addToCart($sessions['guest'], $menu['Penne Arrabbiata'], 1, 17),
                'aperol' => $this->addToCart($sessions['guest'], $menu['Aperol Spritz'], 1, 17),
            ],
        ];

        // ── Each guest turns their basket into a draft order ─────────────────

        $orders = [];

        foreach ($sessions as $key => $session) {
            $orders[$key] = $this->createDraftOrder($vendor, $session, $table);
        }

        foreach ($carts as $key => $items) {
            foreach ($items as $cartItem) {
                $cartItem->update([
                    'order_id' => $orders[$key]->id,
                    'received_at' => now()->subMinutes(15),
                ]);
            }
        }

        // ── Two items get split across the table ─────────────────────────────
        //  • Anna's Prosecco DOC → shared with Max (2 ways)
        //  • Max's Tiramisu      → shared with Anna and the guest (3 ways)

        $carts['anna']['prosecco']->update(['shared_order_ids' => [$orders['max']->id]]);
        $carts['max']['tiramisu']->update(['shared_order_ids' => [$orders['anna']->id, $orders['guest']->id]]);

        // ── Confirm all three orders; payment is still open ──────────────────

        foreach ($orders as $key => $order) {
            $this->confirmOrder($vendor, $order, $sessions[$key]);
        }

        // The kitchen has already picked up the first two courses.
        foreach (['carbonara', 'bruschetta'] as $item) {
            $carts['anna'][$item]->update([
                'preparing_start_at' => now()->subMinutes(12),
                'ready_at' => now()->subMinutes(3),
            ]);
        }

        $this->command->info('TableSessionFlowSeeder: live table seeded.');
        $this->command->info(sprintf('  %s — table %d, PIN %s', $vendor->name, $table->number, self::TABLE_PIN));

        foreach ($orders as $key => $order) {
            $this->command->info(sprintf('  %-5s → %s  %s %s', $key, $order->order_public_id, $order->currency, $order->fresh()->amount));
        }

        $this->command->info("  Sharing: Anna's Prosecco split 2 ways; Max's Tiramisu split 3 ways.");
    }

    // ─── Steps ──────────────────────────────────────────────────────────────

    private function openSession(Vendor $vendor, RestaurantTable $table, Customer $customer, int $minutesAgo): TableScanSession
    {
        return TableScanSession::create([
            'vendor_id' => $vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id' => $customer->id,
            'type' => 'dine_in',
            'pin' => self::TABLE_PIN,
            'status' => 'active',
            'scanned_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    private function addToCart(TableScanSession $session, MenuItem $item, int $quantity, int $minutesAgo): CartItem
    {
        return CartItem::create([
            'table_scan_session_id' => $session->id,
            'menu_item_id' => $item->id,
            'client_item_id' => (string) Str::uuid(),
            'quantity' => $quantity,
            'created_at' => now()->subMinutes($minutesAgo),
            'updated_at' => now()->subMinutes($minutesAgo),
        ]);
    }

    private function createDraftOrder(Vendor $vendor, TableScanSession $session, RestaurantTable $table): Order
    {
        return Order::create([
            'order_public_id' => 'ord-'.Str::random(12),
            'customer_id' => $session->customer_id,
            'vendor_id' => $vendor->id,
            'table_scan_session_id' => $session->id,
            'status' => Order::STATUS_DRAFT,
            'draft_at' => now()->subMinutes(16),
            'amount' => 0,
            'currency' => $vendor->resolveCurrency(),
            'order_type' => 'dine-in',
            'table_number' => $table->number,
            'placed_by' => 'customer',
            'payment_pending' => true,
            'payment_received' => false,
        ]);
    }

    private function confirmOrder(Vendor $vendor, Order $order, TableScanSession $session): void
    {
        $itemsTotal = $this->orderItemsTotal($order, $session, $vendor->country ?? 'AT');
        $serviceFee = round($itemsTotal * ((float) ($vendor->vendorSetting?->service_fee_rate ?? 0) / 100), 2);

        $order->update([
            'status' => Order::STATUS_CONFIRMED,
            'confirmed_at' => now()->subMinutes(14),
            'amount' => round($itemsTotal + $serviceFee, 2),
            'service_fee' => $serviceFee,
            'vat_amount' => $this->orderVatTotal($order, $session, $vendor->country ?? 'AT'),
        ]);
    }

    // ─── Bill splitting ─────────────────────────────────────────────────────

    /**
     * Mirrors CartController::computeOrderAmount — an order owes its own items
     * plus a share of every item another guest split with it.
     *
     * @return array<int, array{item: CartItem, share: float}>
     */
    private function shareLines(Order $order, TableScanSession $session, string $country): array
    {
        $owned = CartItem::with('menuItem')
            ->where('table_scan_session_id', $session->id)
            ->get();

        $sharedIn = CartItem::with('menuItem')
            ->where('table_scan_session_id', '!=', $session->id)
            ->get()
            ->filter(fn (CartItem $item) => in_array($order->id, $item->shared_order_ids ?? [], true));

        return $owned->merge($sharedIn)
            ->map(function (CartItem $item) use ($country) {
                $lineTotal = TaxCalculationService::cartItemLineTotalGross($item, $country);
                $shareCount = 1 + count($item->shared_order_ids ?? []);

                return ['item' => $item, 'share' => $lineTotal / $shareCount];
            })
            ->values()
            ->all();
    }

    private function orderItemsTotal(Order $order, TableScanSession $session, string $country): float
    {
        $total = array_sum(array_column($this->shareLines($order, $session, $country), 'share'));

        return round($total, 2);
    }

    private function orderVatTotal(Order $order, TableScanSession $session, string $country): float
    {
        $vat = 0.0;

        foreach ($this->shareLines($order, $session, $country) as $line) {
            $vatRate = TaxCalculationService::itemVatRate($line['item']->menuItem, $country);
            $vat += TaxCalculationService::vatFromGross($line['share'], $vatRate);
        }

        return round($vat, 2);
    }

    // ─── Cleanup ────────────────────────────────────────────────────────────

    /**
     * Drop any open session on this table so re-running does not stack guests.
     */
    private function clearOpenSessions(RestaurantTable $table): void
    {
        $sessionIds = TableScanSession::where('restaurant_table_id', $table->id)
            ->where('status', 'active')
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return;
        }

        Order::whereIn('table_scan_session_id', $sessionIds)->delete();
        TableScanSession::whereIn('id', $sessionIds)->delete();
    }
}
