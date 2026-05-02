<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\RestaurantTable;
use App\Models\Review;
use App\Models\Vendor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SeedController extends Controller
{
    private const ORDER_TYPES    = ['dine-in', 'takeaway'];
    private const ORDER_STATUSES = ['pending', 'confirmed', 'preparing', 'ready', 'delivered'];
    private const COURSES        = ['drinks', 'appetizers', 'mains', 'desserts'];

    /**
     * POST /api/seed
     * Seed demo orders, reviews, and reservations for the authenticated vendor.
     * Pass ?force=true to delete existing demo data first.
     */
    public function seed(Request $request): JsonResponse
    {
        /** @var Vendor $vendor */
        $vendor = $request->user();
        if (! $vendor || $vendor->getTable() !== 'vendors') {
            abort(401, 'Vendor authentication required');
        }

        $force = $request->boolean('force');

        if ($force) {
            $vendor->orders()->delete();
            $vendor->reviews()->delete();
            $vendor->reservations()->delete();
        }

        // Ensure the vendor has at least one dummy customer to attach orders to
        $customer = Customer::firstOrCreate(
            ['email' => "demo-customer@{$vendor->vendor_public_id}.example"],
            [
                'customer_public_id' => 'cust-' . Str::random(8),
                'name'               => 'Demo Customer',
                'phone'              => '+1 555 0100',
                'password'           => bcrypt(Str::random(16)),
            ]
        );

        // ---- Orders ----
        $orderCount = 50;
        for ($i = 0; $i < $orderCount; $i++) {
            $createdAt = Carbon::now()->subDays(rand(0, 30))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
            $status    = self::ORDER_STATUSES[array_rand(self::ORDER_STATUSES)];
            $orderType = self::ORDER_TYPES[array_rand(self::ORDER_TYPES)];
            $tableNum  = $orderType === 'dine-in' ? rand(1, 20) : null;
            $amount    = rand(12, 120) + (rand(0, 99) / 100);

            Order::create([
                'order_public_id' => 'ord-' . Str::random(12),
                'vendor_id'       => $vendor->id,
                'customer_id'     => $customer->id,
                'status'          => $status,
                'amount'          => round($amount, 2),
                'currency'        => 'EUR',
                'payment_method'  => ['cash', 'card', 'apple_pay'][rand(0, 2)],
                'payment_pending' => $status === 'ready',
                'payment_received' => in_array($status, ['delivered', 'picked_up'], true),
                'order_number'    => '#' . (9000 + $i),
                'order_type'      => $orderType,
                'table_number'    => $tableNum ? (string) $tableNum : null,
                'course'          => self::COURSES[array_rand(self::COURSES)],
                'created_at'      => $createdAt,
                'updated_at'      => $createdAt,
            ]);
        }

        // ---- Reviews ----
        $reviewMessages = [
            'Great food and service!',
            'Very fast preparation.',
            'Food was a bit cold.',
            'Excellent quality, will order again.',
            'Portion sizes could be bigger.',
            'Friendly staff, delicious menu.',
        ];
        for ($i = 0; $i < 20; $i++) {
            $createdAt = Carbon::now()->subDays(rand(0, 30));
            Review::create([
                'vendor_id'   => $vendor->id,
                'customer_id' => $customer->id,
                'rating'      => rand(3, 5),
                'message'     => $reviewMessages[array_rand($reviewMessages)],
                'is_anonymous' => false,
                'created_at'  => $createdAt,
                'updated_at'  => $createdAt,
            ]);
        }

        // ---- Reservations ----
        for ($i = 0; $i < 15; $i++) {
            $date = Carbon::now()->addDays(rand(-7, 14))->setHour(rand(11, 21))->setMinute(0)->setSecond(0);
            Reservation::create([
                'vendor_id'   => $vendor->id,
                'customer_id' => $customer->id,
                'datetime'    => $date,
                'party_size'  => rand(2, 8),
                'status'      => ['pending', 'confirmed', 'cancelled'][rand(0, 2)],
                'notes'       => rand(0, 1) ? 'Window seat preferred' : null,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        // ---- Restaurant tables (ensure at least 20 exist) ----
        if ($vendor->restaurantTables()->count() === 0) {
            for ($n = 1; $n <= 20; $n++) {
                $vendor->restaurantTables()->create([
                    'number'        => $n,
                    'name'          => "Table {$n}",
                    'qr_token'      => RestaurantTable::generateQrToken(),
                    'is_active'     => true,
                    'qr_created_at' => now(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Demo data seeded successfully',
            'seeded'  => [
                'orders'       => $orderCount,
                'reviews'      => 20,
                'reservations' => 15,
            ],
        ]);
    }
}
