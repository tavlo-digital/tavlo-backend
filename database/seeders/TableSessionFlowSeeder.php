<?php

namespace Database\Seeders;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableScanSession;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TableSessionFlowSeeder extends Seeder
{
    private const QR_TOKEN   = '46933ffe-5b9a-4cd2-af31-d58ad0327225';
    private const TABLE_ID   = 11;
    private const VENDOR_ID  = 5;
    private const TABLE_PIN  = '1234';

    public function run(): void
    {
        // ── Prerequisites ───────────────────────────────────────────────────

        $vendor = Vendor::find(self::VENDOR_ID);
        if (! $vendor) {
            $this->command->error('Vendor id=' . self::VENDOR_ID . ' not found. Run VendorSeeder first.');
            return;
        }

        $table = RestaurantTable::find(self::TABLE_ID);
        if (! $table) {
            $this->command->error('RestaurantTable id=' . self::TABLE_ID . ' not found. Run VendorSeeder first.');
            return;
        }

        // Ensure the table carries the correct QR token and is active
        $table->update(['qr_token' => self::QR_TOKEN, 'is_active' => true]);

        // 3 customers: 2 registered, 1 guest
        $anna  = Customer::where('customer_public_id', 'C-1024')->first(); // registered
        $max   = Customer::where('customer_public_id', 'C-2048')->first(); // registered
        $guest = Customer::where('customer_public_id', 'C-4096')->first(); // guest

        if (! $anna || ! $max || ! $guest) {
            $this->command->error('Required customers not found. Run CustomerSeeder first.');
            return;
        }

        // Menu items from Bella Italia
        $items = $this->resolveMenuItems($vendor->id, [
            'Spaghetti Carbonara',
            'Bruschetta al Pomodoro',
            'Prosecco DOC',
            'Margherita Pizza',
            'Fresh Lemonade',
            'Tiramisu',
            'Penne Arrabbiata',
            'Aperol Spritz',
        ]);

        if (! $items) {
            $this->command->error('Required menu items not found. Run MenuSeeder first.');
            return;
        }

        [
            $carbonara, $bruschetta, $prosecco,
            $pizza, $lemonade, $tiramisu,
            $penne, $aperol,
        ] = $items;

        // ── Clean up any existing active sessions for this table ─────────────

        $existing = TableScanSession::where('restaurant_table_id', self::TABLE_ID)
            ->where('status', 'active')
            ->get();

        foreach ($existing as $s) {
            Order::where('table_scan_session_id', $s->id)->delete();
            CartItem::where('table_scan_session_id', $s->id)->delete();
            $s->delete();
        }

        // ── Step 1: Anna scans the QR code → opens the session with a PIN ──

        $sessionAnna = TableScanSession::create([
            'vendor_id'           => $vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id'         => $anna->id,
            'pin'                 => self::TABLE_PIN,
            'status'              => 'active',
            'scanned_at'          => now()->subMinutes(30),
        ]);

        // ── Step 2: Max joins the session using the PIN ─────────────────────

        $sessionMax = TableScanSession::create([
            'vendor_id'           => $vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id'         => $max->id,
            'pin'                 => self::TABLE_PIN,
            'status'              => 'active',
            'scanned_at'          => now()->subMinutes(28),
        ]);

        // ── Step 3: Guest joins the session using the PIN ───────────────────

        $sessionGuest = TableScanSession::create([
            'vendor_id'           => $vendor->id,
            'restaurant_table_id' => $table->id,
            'customer_id'         => $guest->id,
            'pin'                 => self::TABLE_PIN,
            'status'              => 'active',
            'scanned_at'          => now()->subMinutes(25),
        ]);

        // ── Step 4: Each customer adds items to their personal cart ─────────

        // Anna: Spaghetti Carbonara × 1, Bruschetta × 1, Prosecco DOC × 1
        $annaCartCarbonara  = CartItem::create(['table_scan_session_id' => $sessionAnna->id, 'menu_item_id' => $carbonara->id,  'quantity' => 1]);
        $annaCartBruschetta = CartItem::create(['table_scan_session_id' => $sessionAnna->id, 'menu_item_id' => $bruschetta->id, 'quantity' => 1]);
        $annaCartProsecco   = CartItem::create(['table_scan_session_id' => $sessionAnna->id, 'menu_item_id' => $prosecco->id,   'quantity' => 1]);

        // Max: Margherita Pizza × 1, Fresh Lemonade × 2, Tiramisu × 1
        $maxCartPizza    = CartItem::create(['table_scan_session_id' => $sessionMax->id, 'menu_item_id' => $pizza->id,    'quantity' => 1]);
        $maxCartLemonade = CartItem::create(['table_scan_session_id' => $sessionMax->id, 'menu_item_id' => $lemonade->id, 'quantity' => 2]);
        $maxCartTiramisu = CartItem::create(['table_scan_session_id' => $sessionMax->id, 'menu_item_id' => $tiramisu->id, 'quantity' => 1]);

        // Guest: Penne Arrabbiata × 1, Aperol Spritz × 1
        $guestCartPenne  = CartItem::create(['table_scan_session_id' => $sessionGuest->id, 'menu_item_id' => $penne->id,   'quantity' => 1]);
        $guestCartAperol = CartItem::create(['table_scan_session_id' => $sessionGuest->id, 'menu_item_id' => $aperol->id,  'quantity' => 1]);

        // ── Step 5: Each customer creates a draft order ─────────────────────

        $annaOrder  = $this->makeDraftOrder($anna->id,  $vendor->id, $sessionAnna->id);
        $maxOrder   = $this->makeDraftOrder($max->id,   $vendor->id, $sessionMax->id);
        $guestOrder = $this->makeDraftOrder($guest->id, $vendor->id, $sessionGuest->id);

        // ── Step 6: Wire share relationships via cart_items.order_ids ───────
        //
        // Sharing scenario
        //  • Anna's Prosecco DOC  →  split between Anna + Max (2 people)
        //  • Max's Tiramisu       →  split between Anna + Max + Guest (3 people)

        $annaCartProsecco->update(['order_ids' => [$maxOrder->id]]);
        $maxCartTiramisu->update(['order_ids' => [$annaOrder->id, $guestOrder->id]]);

        // ── Step 7: Confirm each order ──────────────────────────────────────

        $annaFinal  = $this->calcOrderAmount($annaOrder->id,  $sessionAnna->id);
        $maxFinal   = $this->calcOrderAmount($maxOrder->id,   $sessionMax->id);
        $guestFinal = $this->calcOrderAmount($guestOrder->id, $sessionGuest->id);

        $annaOrder->update(['status'  => 'confirmed', 'amount' => $annaFinal]);
        $maxOrder->update(['status'   => 'confirmed', 'amount' => $maxFinal]);
        $guestOrder->update(['status' => 'confirmed', 'amount' => $guestFinal]);

        // ── Summary ──────────────────────────────────────────────────────────

        $this->command->info('Table session flow seeded.');
        $this->command->info("  Table #{$table->id} — PIN: " . self::TABLE_PIN);
        $this->command->info("  Anna  (registered) — order #{$annaOrder->order_public_id}  → €{$annaFinal}");
        $this->command->info("  Max   (registered) — order #{$maxOrder->order_public_id}  → €{$maxFinal}");
        $this->command->info("  Guest (guest)       — order #{$guestOrder->order_public_id}  → €{$guestFinal}");
        $this->command->info('  Sharing: Anna\'s Prosecco DOC split 2-ways; Max\'s Tiramisu split 3-ways.');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /** Mirrors CartController::computeOrderAmount for seed-side parity. */
    private function calcOrderAmount(int $orderId, int $ownerSessionId): float
    {
        $owned = CartItem::with('menuItem:id,price')
            ->where('table_scan_session_id', $ownerSessionId)
            ->get();

        $sharedInto = CartItem::with('menuItem:id,price')
            ->whereJsonContains('order_ids', $orderId)
            ->where('table_scan_session_id', '!=', $ownerSessionId)
            ->get();

        $total = 0.0;
        foreach ($owned->merge($sharedInto) as $ci) {
            $unit       = $ci->menuItem ? (float) $ci->menuItem->price : 0.0;
            $lineTotal  = $unit * $ci->quantity;
            $shareCount = 1 + count($ci->order_ids ?? []);
            $total     += $lineTotal / $shareCount;
        }

        return round($total, 2);
    }

    private function makeDraftOrder(int $customerId, int $vendorId, int $sessionId): Order
    {
        return Order::create([
            'order_public_id'       => 'ord-' . Str::random(12),
            'customer_id'           => $customerId,
            'vendor_id'             => $vendorId,
            'table_scan_session_id' => $sessionId,
            'status'                => 'draft',
            'amount'                => 0,
            'currency'              => 'EUR',
            'payment_pending'       => true,
            'payment_received'      => false,
            'order_type'            => 'dine-in',
        ]);
    }

    /**
     * Returns the MenuItem instances in the same order as $names, or null on any miss.
     * Prefers items belonging to $vendorId; falls back to any vendor for demo purposes.
     */
    private function resolveMenuItems(int $vendorId, array $names): ?array
    {
        $resolved = [];

        foreach ($names as $name) {
            $item = MenuItem::where('name', $name)->where('vendor_id', $vendorId)->first()
                ?? MenuItem::where('name', $name)->first();

            if (! $item) {
                return null;
            }

            $resolved[] = $item;
        }

        return $resolved;
    }
}
