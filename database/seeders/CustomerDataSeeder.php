<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\GdprRequest;
use App\Models\Order;
use App\Models\Refund;
use App\Models\Review;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class CustomerDataSeeder extends Seeder
{
    public function run(): void
    {
        $anna = Customer::where('customer_public_id', 'C-1024')->first();
        $max = Customer::where('customer_public_id', 'C-2048')->first();
        $sophie = Customer::where('customer_public_id', 'C-3072')->first();
        $guest = Customer::where('customer_public_id', 'C-4096')->first();
        $thomas = Customer::where('customer_public_id', 'C-5120')->first();

        $vendors = Vendor::all();
        $v1 = $vendors->first();
        $v2 = $vendors->skip(1)->first() ?? $v1;
        $v3 = $vendors->skip(2)->first() ?? $v1;

        if (!$anna || !$v1) {
            return;
        }

        // Clean up existing seeded data for these customers to allow re-seeding
        $customerIds = collect([$anna, $max, $sophie, $guest, $thomas])
            ->filter()
            ->pluck('id')
            ->toArray();

        CustomerActivity::whereIn('customer_id', $customerIds)->delete();
        GdprRequest::whereIn('customer_id', $customerIds)->delete();
        Review::whereIn('customer_id', $customerIds)->delete();
        Refund::whereIn('customer_id', $customerIds)->delete();
        Order::whereIn('customer_id', $customerIds)->delete();

        // ─── Orders for Anna (C-1024) ───
        $o1 = Order::create([
            'order_public_id' => 'ORD-8472',
            'customer_id' => $anna->id,
            'vendor_id' => $v1->id,
            'status' => 'delivered',
            'items_count' => 3,
            'amount' => 34.50,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'transaction_id' => 'txn_8472_001',
            'created_at' => now()->subDays(1)->setTime(13, 15),
        ]);

        $o2 = Order::create([
            'order_public_id' => 'ORD-8401',
            'customer_id' => $anna->id,
            'vendor_id' => $v2->id,
            'status' => 'delivered',
            'items_count' => 5,
            'amount' => 52.90,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'transaction_id' => 'txn_8401_001',
            'created_at' => now()->subDays(3)->setTime(19, 30),
        ]);

        $o3 = Order::create([
            'order_public_id' => 'ORD-8324',
            'customer_id' => $anna->id,
            'vendor_id' => $v3->id,
            'status' => 'cancelled',
            'items_count' => 2,
            'amount' => 28.00,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'transaction_id' => 'txn_8324_001',
            'created_at' => now()->subDays(5)->setTime(18, 45),
        ]);

        $o4 = Order::create([
            'order_public_id' => 'ORD-8256',
            'customer_id' => $anna->id,
            'vendor_id' => $v1->id,
            'status' => 'delivered',
            'items_count' => 4,
            'amount' => 41.20,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'transaction_id' => 'txn_8256_001',
            'created_at' => now()->subDays(8)->setTime(12, 20),
        ]);

        $o5 = Order::create([
            'order_public_id' => 'ORD-8198',
            'customer_id' => $anna->id,
            'vendor_id' => $v1->id,
            'status' => 'delivered',
            'items_count' => 6,
            'amount' => 67.80,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'transaction_id' => 'txn_8198_001',
            'created_at' => now()->subDays(10)->setTime(20, 10),
        ]);

        Order::create([
            'order_public_id' => 'ORD-8142',
            'customer_id' => $anna->id,
            'vendor_id' => $v2->id,
            'status' => 'delivered',
            'items_count' => 3,
            'amount' => 45.30,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'transaction_id' => 'txn_8142_001',
            'created_at' => now()->subDays(13)->setTime(14, 0),
        ]);

        // ─── Orders for Max (C-2048) ───
        Order::create([
            'order_public_id' => 'ORD-9001',
            'customer_id' => $max->id,
            'vendor_id' => $v1->id,
            'status' => 'delivered',
            'items_count' => 2,
            'amount' => 25.00,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'transaction_id' => 'txn_9001_001',
            'created_at' => now()->subDays(2)->setTime(12, 0),
        ]);

        Order::create([
            'order_public_id' => 'ORD-9002',
            'customer_id' => $max->id,
            'vendor_id' => $v2->id,
            'status' => 'delivered',
            'items_count' => 4,
            'amount' => 48.50,
            'currency' => 'EUR',
            'payment_method' => 'paypal',
            'transaction_id' => 'txn_9002_001',
            'created_at' => now()->subDays(5)->setTime(19, 45),
        ]);

        // ─── Orders for Sophie (C-3072) ───
        $so1 = Order::create([
            'order_public_id' => 'ORD-7501',
            'customer_id' => $sophie->id,
            'vendor_id' => $v3->id,
            'status' => 'delivered',
            'items_count' => 1,
            'amount' => 150.00,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'transaction_id' => 'txn_7501_001',
            'created_at' => now()->subDays(15)->setTime(11, 30),
        ]);

        $so2 = Order::create([
            'order_public_id' => 'ORD-7502',
            'customer_id' => $sophie->id,
            'vendor_id' => $v1->id,
            'status' => 'delivered',
            'items_count' => 2,
            'amount' => 200.00,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'transaction_id' => 'txn_7502_001',
            'created_at' => now()->subDays(20)->setTime(14, 0),
        ]);

        Order::create([
            'order_public_id' => 'ORD-7503',
            'customer_id' => $sophie->id,
            'vendor_id' => $v2->id,
            'status' => 'pending',
            'items_count' => 3,
            'amount' => 100.00,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'transaction_id' => 'txn_7503_001',
            'created_at' => now()->subDays(1)->setTime(10, 0),
        ]);

        // ─── Order for Guest (C-4096) ───
        Order::create([
            'order_public_id' => 'ORD-6001',
            'customer_id' => $guest->id,
            'vendor_id' => $v1->id,
            'status' => 'delivered',
            'items_count' => 1,
            'amount' => 28.90,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'transaction_id' => 'txn_6001_001',
            'created_at' => now()->subDays(1)->setTime(17, 30),
        ]);

        // ─── Orders for Thomas (C-5120) ───
        $to1 = Order::create([
            'order_public_id' => 'ORD-7001',
            'customer_id' => $thomas->id,
            'vendor_id' => $v1->id,
            'status' => 'delivered',
            'items_count' => 3,
            'amount' => 55.00,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'transaction_id' => 'txn_7001_001',
            'created_at' => now()->subDays(3)->setTime(12, 0),
        ]);

        Order::create([
            'order_public_id' => 'ORD-7002',
            'customer_id' => $thomas->id,
            'vendor_id' => $v2->id,
            'status' => 'delivered',
            'items_count' => 2,
            'amount' => 42.00,
            'currency' => 'EUR',
            'payment_method' => 'card',
            'transaction_id' => 'txn_7002_001',
            'created_at' => now()->subDays(7)->setTime(18, 30),
        ]);

        // ─── Refunds ───
        Refund::create([
            'refund_public_id' => 'DIS-1024',
            'customer_id' => $anna->id,
            'order_id' => $o3->id,
            'type' => 'dispute',
            'status' => 'open',
            'amount' => 28.00,
            'currency' => 'EUR',
            'reason' => 'Card declined but still charged',
            'description' => 'Payment failed during checkout but amount was deducted from account',
            'created_at' => now()->subDays(4),
        ]);

        Refund::create([
            'refund_public_id' => 'REF-2048',
            'customer_id' => $anna->id,
            'order_id' => $o2->id,
            'type' => 'refund',
            'status' => 'approved',
            'amount' => 38.50,
            'currency' => 'EUR',
            'reason' => 'Food quality issue',
            'description' => 'Sushi was not fresh, customer complained immediately after delivery',
            'created_at' => now()->subDays(22),
        ]);

        // Thomas refunds (flagged customer)
        Refund::create([
            'refund_public_id' => 'REF-3001',
            'customer_id' => $thomas->id,
            'order_id' => $to1->id,
            'type' => 'refund',
            'status' => 'pending',
            'amount' => 55.00,
            'currency' => 'EUR',
            'reason' => 'Order arrived late and cold',
            'description' => 'Delivery took over 90 minutes, food was inedible',
            'created_at' => now()->subDays(2),
        ]);

        // Sophie dispute
        Refund::create([
            'refund_public_id' => 'DIS-3072',
            'customer_id' => $sophie->id,
            'order_id' => $so1->id,
            'type' => 'dispute',
            'status' => 'open',
            'amount' => 150.00,
            'currency' => 'EUR',
            'reason' => 'Unauthorized charge',
            'description' => 'Customer claims they did not place this order',
            'created_at' => now()->subDays(10),
        ]);

        // ─── Reviews for Anna ───
        Review::create([
            'review_public_id' => 'REV-501',
            'customer_id' => $anna->id,
            'vendor_id' => $v1->id,
            'order_id' => $o1->id,
            'rating' => 5,
            'text' => 'Amazing pizza! Fast delivery and still hot. Will order again.',
            'flagged' => false,
            'created_at' => now()->subDays(1),
        ]);

        Review::create([
            'review_public_id' => 'REV-502',
            'customer_id' => $anna->id,
            'vendor_id' => $v2->id,
            'order_id' => $o2->id,
            'rating' => 4,
            'text' => 'Good quality sushi, delivery was a bit slow.',
            'flagged' => false,
            'created_at' => now()->subDays(3),
        ]);

        Review::create([
            'review_public_id' => 'REV-503',
            'customer_id' => $anna->id,
            'vendor_id' => $v3->id,
            'order_id' => $o3->id,
            'rating' => 1,
            'text' => 'Terrible experience! Order never arrived but I was charged!',
            'flagged' => true,
            'flag_reason' => 'Potential abuse – aggressive language',
            'created_at' => now()->subDays(5),
        ]);

        Review::create([
            'review_public_id' => 'REV-504',
            'customer_id' => $anna->id,
            'vendor_id' => $v1->id,
            'order_id' => $o4->id,
            'rating' => 5,
            'text' => 'Best burgers in Vienna! Perfectly cooked.',
            'flagged' => false,
            'created_at' => now()->subDays(8),
        ]);

        Review::create([
            'review_public_id' => 'REV-505',
            'customer_id' => $anna->id,
            'vendor_id' => $v1->id,
            'order_id' => $o5->id,
            'rating' => 4,
            'text' => 'Great pasta, generous portions.',
            'flagged' => false,
            'created_at' => now()->subDays(10),
        ]);

        Review::create([
            'review_public_id' => 'REV-506',
            'customer_id' => $anna->id,
            'vendor_id' => $v2->id,
            'rating' => 5,
            'text' => 'Excellent pad thai, authentic taste!',
            'flagged' => false,
            'created_at' => now()->subDays(13),
        ]);

        Review::create([
            'review_public_id' => 'REV-507',
            'customer_id' => $anna->id,
            'vendor_id' => $v2->id,
            'rating' => 3,
            'text' => 'Average sushi, expected better quality for the price.',
            'flagged' => false,
            'created_at' => now()->subDays(18),
        ]);

        Review::create([
            'review_public_id' => 'REV-508',
            'customer_id' => $anna->id,
            'vendor_id' => $v3->id,
            'rating' => 2,
            'text' => 'Pizza was cold and soggy when it arrived.',
            'flagged' => true,
            'flag_reason' => 'Vendor complaint – claims order was fine',
            'created_at' => now()->subDays(20),
        ]);

        // Reviews for Max
        Review::create([
            'review_public_id' => 'REV-601',
            'customer_id' => $max->id,
            'vendor_id' => $v1->id,
            'rating' => 5,
            'text' => 'Consistently great food and fast delivery!',
            'flagged' => false,
            'created_at' => now()->subDays(2),
        ]);

        // ─── Activity Logs for Anna ───
        $annaActivities = [
            ['event_type' => 'login', 'title' => 'Login', 'detail' => 'Logged in via mobile app', 'ip_address' => '185.34.xxx.xxx', 'device' => 'iPhone 15', 'created_at' => now()->subDays(1)->setTime(14, 32)],
            ['event_type' => 'order_placed', 'title' => 'Order Placed', 'detail' => 'Order ORD-8472 (€34.50)', 'ip_address' => '185.34.xxx.xxx', 'device' => 'iPhone 15', 'created_at' => now()->subDays(1)->setTime(13, 15)],
            ['event_type' => 'qr_scan', 'title' => 'QR Scan', 'detail' => 'Scanned QR code at table 12', 'ip_address' => '185.34.xxx.xxx', 'device' => 'iPhone 15', 'created_at' => now()->subDays(1)->setTime(13, 14)],
            ['event_type' => 'order_placed', 'title' => 'Order Placed', 'detail' => 'Order ORD-8401 (€52.90)', 'ip_address' => '185.34.xxx.xxx', 'device' => 'iPhone 15', 'created_at' => now()->subDays(3)->setTime(19, 30)],
            ['event_type' => 'login', 'title' => 'Login', 'detail' => 'Logged in via web browser', 'ip_address' => '185.34.xxx.xxx', 'device' => 'Chrome 120', 'created_at' => now()->subDays(3)->setTime(19, 28)],
            ['event_type' => 'dispute_filed', 'title' => 'Dispute Filed', 'detail' => 'Dispute DIS-1024 for Order ORD-8324', 'ip_address' => '185.34.xxx.xxx', 'device' => 'Chrome 120', 'created_at' => now()->subDays(4)->setTime(10, 45)],
            ['event_type' => 'order_cancelled', 'title' => 'Order Cancelled', 'detail' => 'Order ORD-8324 (€28.00)', 'ip_address' => '185.34.xxx.xxx', 'device' => 'Chrome 120', 'created_at' => now()->subDays(5)->setTime(18, 45)],
            ['event_type' => 'order_placed', 'title' => 'Order Placed', 'detail' => 'Order ORD-8256 (€41.20)', 'ip_address' => '185.34.xxx.xxx', 'device' => 'iPhone 15', 'created_at' => now()->subDays(8)->setTime(12, 20)],
            ['event_type' => 'login', 'title' => 'Login', 'detail' => 'Logged in via mobile app', 'ip_address' => '185.34.xxx.xxx', 'device' => 'iPhone 15', 'created_at' => now()->subDays(8)->setTime(12, 18)],
            ['event_type' => 'order_placed', 'title' => 'Order Placed', 'detail' => 'Order ORD-8198 (€67.80)', 'ip_address' => '185.34.xxx.xxx', 'device' => 'iPhone 15', 'created_at' => now()->subDays(10)->setTime(20, 10)],
        ];

        foreach ($annaActivities as $activity) {
            CustomerActivity::create(array_merge(['customer_id' => $anna->id], $activity));
        }

        // Activities for Sophie (flagged)
        $sophieActivities = [
            ['event_type' => 'dispute_filed', 'title' => 'Dispute Filed', 'detail' => 'Dispute DIS-3072 for Order ORD-7501', 'ip_address' => '91.12.xxx.xxx', 'device' => 'Safari 17', 'created_at' => now()->subDays(10)->setTime(9, 0)],
            ['event_type' => 'login', 'title' => 'Login', 'detail' => 'Logged in via web browser', 'ip_address' => '91.12.xxx.xxx', 'device' => 'Safari 17', 'created_at' => now()->subDays(10)->setTime(8, 55)],
            ['event_type' => 'order_placed', 'title' => 'Order Placed', 'detail' => 'Order ORD-7503 (€100.00)', 'ip_address' => '91.12.xxx.xxx', 'device' => 'Safari 17', 'created_at' => now()->subDays(1)->setTime(10, 0)],
        ];

        foreach ($sophieActivities as $activity) {
            CustomerActivity::create(array_merge(['customer_id' => $sophie->id], $activity));
        }

        // Activities for Thomas (flagged)
        $thomasActivities = [
            ['event_type' => 'refund_requested', 'title' => 'Refund Requested', 'detail' => 'Refund REF-3001 for Order ORD-7001', 'ip_address' => '78.45.xxx.xxx', 'device' => 'Android 14', 'created_at' => now()->subDays(2)->setTime(14, 0)],
            ['event_type' => 'order_placed', 'title' => 'Order Placed', 'detail' => 'Order ORD-7001 (€55.00)', 'ip_address' => '78.45.xxx.xxx', 'device' => 'Android 14', 'created_at' => now()->subDays(3)->setTime(12, 0)],
            ['event_type' => 'login', 'title' => 'Login', 'detail' => 'Logged in via mobile app', 'ip_address' => '78.45.xxx.xxx', 'device' => 'Android 14', 'created_at' => now()->subDays(3)->setTime(11, 55)],
        ];

        foreach ($thomasActivities as $activity) {
            CustomerActivity::create(array_merge(['customer_id' => $thomas->id], $activity));
        }

        // ─── GDPR Requests ───
        GdprRequest::create([
            'customer_id' => $sophie->id,
            'type' => 'data_export',
            'status' => 'completed',
            'reason' => 'Customer requested full data export',
            'completed_at' => now()->subDays(5),
            'created_at' => now()->subDays(8),
        ]);

        GdprRequest::create([
            'customer_id' => $sophie->id,
            'type' => 'account_deletion',
            'status' => 'pending',
            'reason' => 'Customer wants account removed after dispute',
            'created_at' => now()->subDays(2),
        ]);

        GdprRequest::create([
            'customer_id' => $thomas->id,
            'type' => 'data_export',
            'status' => 'pending',
            'reason' => 'Requested copy of all personal data',
            'created_at' => now()->subDays(1),
        ]);
    }
}
