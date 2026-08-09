<?php

namespace Database\Seeders;

use App\Models\CartItem;
use App\Models\Customer;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableScanSession;
use App\Models\Vendor;
use App\Services\TaxCalculationService;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A live takeaway queue at Burger Palace: three pickup orders sitting at
 * different stages so the vendor pickup screen and KDS have work in progress.
 *
 * The dine-in counterpart is TableSessionFlowSeeder.
 */
class OrderSeeder extends Seeder
{
    private const VENDOR_SLUG = 'burger-palace';

    public function run(): void
    {
        $vendor = Vendor::with('vendorSetting')->where('slug', self::VENDOR_SLUG)->first();

        if (! $vendor) {
            $this->command->warn('OrderSeeder: '.self::VENDOR_SLUG.' not found — run VendorSeeder first.');

            return;
        }

        $menu = MenuItem::where('vendor_id', $vendor->id)->get()->keyBy('name');

        if ($menu->isEmpty()) {
            $this->command->warn('OrderSeeder: '.self::VENDOR_SLUG.' has no menu — run MenuSeeder first.');

            return;
        }

        $queue = [
            [
                'customer' => 'C-2048',
                'stage' => 'confirmed',      // waiting for the kitchen to pick it up
                'minutes_ago' => 4,
                'items' => [['Classic Cheeseburger', 1], ['Hand-cut Fries', 1]],
            ],
            [
                'customer' => 'C-5120',
                'stage' => 'in_progress',    // on the grill
                'minutes_ago' => 11,
                'items' => [['Bacon BBQ Burger', 2], ['Craft Cola', 2]],
            ],
            [
                'customer' => 'C-4096',
                'stage' => 'ready',          // bagged, waiting at the counter
                'minutes_ago' => 18,
                'items' => [['Garden Veggie Burger', 1], ['Crispy Onion Rings', 1]],
            ],
        ];

        $seeded = 0;

        foreach ($queue as $entry) {
            $customer = Customer::where('customer_public_id', $entry['customer'])->first();

            if (! $customer) {
                continue;
            }

            $this->clearOpenPickupSession($vendor, $customer);

            if ($this->seedPickupOrder($vendor, $customer, $menu, $entry)) {
                $seeded++;
            }
        }

        $this->command->info("OrderSeeder: {$seeded} live pickup orders seeded for {$vendor->name}.");
    }

    /**
     * @param  Collection<string, MenuItem>  $menu
     * @param  array<string, mixed>  $entry
     */
    private function seedPickupOrder(Vendor $vendor, Customer $customer, $menu, array $entry): bool
    {
        $scannedAt = now()->subMinutes($entry['minutes_ago']);

        $session = TableScanSession::create([
            'vendor_id' => $vendor->id,
            'restaurant_table_id' => null,
            'customer_id' => $customer->id,
            'type' => 'pickup',
            'pin' => '',
            'status' => 'active',
            'scanned_at' => $scannedAt,
        ]);

        $order = Order::create([
            'order_public_id' => 'ord-'.Str::random(12),
            'customer_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'table_scan_session_id' => $session->id,
            'status' => Order::STATUS_DRAFT,
            'draft_at' => $scannedAt,
            'amount' => 0,
            'currency' => $vendor->resolveCurrency(),
            'order_type' => 'takeaway',
            'placed_by' => 'customer',
            'payment_pending' => true,
            'payment_received' => false,
        ]);

        $itemsTotal = 0.0;
        $vatTotal = 0.0;
        $country = $vendor->country ?? 'AT';
        $cartItems = [];

        foreach ($entry['items'] as [$name, $quantity]) {
            $menuItem = $menu->get($name);

            if (! $menuItem) {
                continue;
            }

            $cartItem = CartItem::create([
                'table_scan_session_id' => $session->id,
                'menu_item_id' => $menuItem->id,
                'order_id' => $order->id,
                'client_item_id' => (string) Str::uuid(),
                'quantity' => $quantity,
                'received_at' => $scannedAt->copy()->addMinutes(2),
                'created_at' => $scannedAt,
                'updated_at' => $scannedAt->copy()->addMinutes(2),
            ]);

            $cartItem->setRelation('menuItem', $menuItem);

            $lineTotal = TaxCalculationService::cartItemLineTotalGross($cartItem, $country);
            $itemsTotal += $lineTotal;
            $vatTotal += TaxCalculationService::vatFromGross(
                $lineTotal,
                TaxCalculationService::itemVatRate($menuItem, $country)
            );

            $cartItems[] = $cartItem;
        }

        if (! $cartItems) {
            $session->delete();

            return false;
        }

        $itemsTotal = round($itemsTotal, 2);
        $serviceFee = round($itemsTotal * ((float) ($vendor->vendorSetting?->service_fee_rate ?? 0) / 100), 2);

        $order->update([
            'status' => $entry['stage'] === 'confirmed' ? Order::STATUS_CONFIRMED : Order::STATUS_IN_PROGRESS,
            'confirmed_at' => $scannedAt->copy()->addMinutes(2),
            'in_progress_at' => $entry['stage'] === 'confirmed' ? null : $scannedAt->copy()->addMinutes(4),
            'amount' => round($itemsTotal + $serviceFee, 2),
            'service_fee' => $serviceFee,
            'vat_amount' => round($vatTotal, 2),
            'order_number' => (int) (Order::where('vendor_id', $vendor->id)->max('order_number') ?? 9000) + 1,
        ]);

        $this->applyStageToItems($cartItems, $entry['stage'], $scannedAt);

        return true;
    }

    /**
     * @param  array<int, CartItem>  $cartItems
     */
    private function applyStageToItems(array $cartItems, string $stage, CarbonInterface $scannedAt): void
    {
        $timestamps = match ($stage) {
            'in_progress' => ['preparing_start_at' => $scannedAt->copy()->addMinutes(4)],
            'ready' => [
                'preparing_start_at' => $scannedAt->copy()->addMinutes(4),
                'ready_at' => $scannedAt->copy()->addMinutes(14),
            ],
            default => [],
        };

        if (! $timestamps) {
            return;
        }

        foreach ($cartItems as $cartItem) {
            $cartItem->update($timestamps);
        }
    }

    /**
     * Pickup sessions are one-per-customer-per-vendor while active.
     */
    private function clearOpenPickupSession(Vendor $vendor, Customer $customer): void
    {
        $sessionIds = TableScanSession::where('vendor_id', $vendor->id)
            ->where('customer_id', $customer->id)
            ->where('type', 'pickup')
            ->where('status', 'active')
            ->pluck('id');

        if ($sessionIds->isEmpty()) {
            return;
        }

        Order::whereIn('table_scan_session_id', $sessionIds)->delete();
        TableScanSession::whereIn('id', $sessionIds)->delete();
    }
}
