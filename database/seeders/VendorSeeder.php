<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPlan;
use App\Models\Vendor;
use App\Models\VendorActivity;
use App\Models\VendorRequestChange;
use App\Models\VendorSetting;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class VendorSeeder extends Seeder
{
    public function run(): void
    {
        $basic = SubscriptionPlan::where('name', 'Basic')->first();
        $standard = SubscriptionPlan::where('name', 'Standard')->first();
        $premium = SubscriptionPlan::where('name', 'Premium')->first();

        $vendorsData = [
            [
                'vendor_public_id' => 'VID-8492',
                'slug' => 'bella-italia',
                'name' => 'Bella Italia',
                'restaurant_name' => 'Bella Italia GmbH',
                'legal_entity_name' => 'Bella Italia Gastronomy GmbH',
                'business_registration_number' => 'FN 123456a',
                'vat_number' => 'ATU12345678',
                'website' => 'https://bellaitalia.at',
                'country' => 'Austria',
                'city' => 'Vienna',
                'address' => 'Kärntner Straße 12',
                'phone' => '+43 1 234 5678',
                'email' => 'contact@bellaitalia.at',
                'status' => 'active',
                'live_status' => 'live',
                'risk_level' => 'red',
                'orders_count' => 284,
                'revenue_total' => 12450.00,
                'users_used' => 7,
                'payment_status' => 'failed',
                'payment_last_success' => now()->subDays(5),
                'payment_failures_24h' => 3,
                'last_activity_at' => now()->subHours(2),
                'plan' => $premium,
                'sub_status' => 'active',
                'billing_cycle' => 'monthly',
            ],
            [
                'vendor_public_id' => 'VID-2847',
                'slug' => 'pizza-express',
                'name' => 'Pizza Express',
                'restaurant_name' => 'Pizza Express KG',
                'legal_entity_name' => 'Pizza Express Gastronomie KG',
                'business_registration_number' => 'FN 284712b',
                'vat_number' => 'ATU28471234',
                'website' => 'https://pizzaexpress.at',
                'country' => 'Austria',
                'city' => 'Salzburg',
                'address' => 'Getreidegasse 8',
                'phone' => '+43 662 123 456',
                'email' => 'info@pizzaexpress.at',
                'status' => 'active',
                'live_status' => 'live',
                'risk_level' => 'orange',
                'orders_count' => 156,
                'revenue_total' => 8920.00,
                'users_used' => 4,
                'payment_status' => 'overdue',
                'payment_last_success' => now()->subDays(34),
                'payment_failures_24h' => 0,
                'last_activity_at' => now()->subDays(1),
                'plan' => $standard,
                'sub_status' => 'expired',
                'billing_cycle' => 'monthly',
            ],
            [
                'vendor_public_id' => 'VID-9471',
                'slug' => 'sakura-sushi',
                'name' => 'Sakura Sushi',
                'restaurant_name' => 'Sakura Sushi e.U.',
                'legal_entity_name' => 'Sakura Sushi e.U.',
                'business_registration_number' => 'FN 947123c',
                'vat_number' => 'ATU94712345',
                'website' => null,
                'country' => 'Austria',
                'city' => 'Vienna',
                'address' => 'Mariahilfer Straße 45',
                'phone' => '+43 1 987 6543',
                'email' => 'hello@sakurasushi.at',
                'status' => 'active',
                'live_status' => 'live',
                'risk_level' => 'yellow',
                'orders_count' => 12,
                'revenue_total' => 890.00,
                'users_used' => 2,
                'payment_status' => 'trial',
                'payment_last_success' => null,
                'payment_failures_24h' => 0,
                'last_activity_at' => now()->subHours(36),
                'plan' => $basic,
                'sub_status' => 'trial',
                'billing_cycle' => 'monthly',
            ],
            [
                'vendor_public_id' => 'VID-1234',
                'slug' => 'green-bowl-cafe',
                'name' => 'Green Bowl Cafe',
                'restaurant_name' => 'Green Bowl Cafe GmbH',
                'legal_entity_name' => 'Green Bowl Cafe GmbH',
                'business_registration_number' => 'FN 123400d',
                'vat_number' => 'ATU12340078',
                'website' => 'https://greenbowlcafe.at',
                'country' => 'Austria',
                'city' => 'Graz',
                'address' => 'Herrengasse 22',
                'phone' => '+43 316 111 222',
                'email' => 'info@greenbowlcafe.at',
                'status' => 'active',
                'live_status' => 'live',
                'risk_level' => 'none',
                'orders_count' => 432,
                'revenue_total' => 18340.00,
                'users_used' => 3,
                'payment_status' => 'paid',
                'payment_last_success' => now()->subDays(2),
                'payment_failures_24h' => 0,
                'last_activity_at' => now()->subHours(6),
                'plan' => $basic,
                'sub_status' => 'active',
                'billing_cycle' => 'monthly',
            ],
            [
                'vendor_public_id' => 'VID-5678',
                'slug' => 'burger-palace',
                'name' => 'Burger Palace',
                'restaurant_name' => 'Burger Palace OG',
                'legal_entity_name' => 'Burger Palace OG',
                'business_registration_number' => 'FN 567800e',
                'vat_number' => 'ATU56780012',
                'website' => 'https://burgerpalace.at',
                'country' => 'Austria',
                'city' => 'Vienna',
                'address' => 'Praterstraße 31',
                'phone' => '+43 1 555 7890',
                'email' => 'hello@burgerpalace.at',
                'status' => 'active',
                'live_status' => 'live',
                'risk_level' => 'none',
                'orders_count' => 892,
                'revenue_total' => 34820.00,
                'users_used' => 12,
                'payment_status' => 'paid',
                'payment_last_success' => now()->subDays(1),
                'payment_failures_24h' => 0,
                'last_activity_at' => now()->subHours(1),
                'plan' => $premium,
                'sub_status' => 'active',
                'billing_cycle' => 'yearly',
            ],
        ];

        foreach ($vendorsData as $data) {
            $plan = $data['plan'];
            $subStatus = $data['sub_status'];
            $billingCycle = $data['billing_cycle'];

            unset($data['plan'], $data['sub_status'], $data['billing_cycle']);

            $vendor = Vendor::where('vendor_public_id', $data['vendor_public_id'])
                ->orWhere('email', $data['email'])
                ->orWhere('phone', $data['phone'])
                ->first();

            if ($vendor) {
                $vendor->update(array_merge($data, ['password' => Hash::make('password')]));
            } else {
                $vendor = Vendor::create(array_merge($data, ['password' => Hash::make('password')]));
            }

            // Ensure vendor_settings exists with is_live_and_discoverable = true
            VendorSetting::updateOrCreate(
                ['vendor_id' => $vendor->id],
                [
                    'is_live_and_discoverable' => true,
                    'currency' => 'EUR',
                    'accept_cash' => true,
                    'accept_card' => true,
                    'enable_reservations' => true,
                    'loyalty_enabled' => true,
                    'points_per_euro' => 20,
                    'minimum_redemption_points' => 100,
                    'point_value' => 0.10,
                    'enable_reviews' => true,
                    'business_hours' => json_encode([
                        'monday'    => ['open' => '10:00', 'close' => '22:00', 'closed' => false],
                        'tuesday'   => ['open' => '10:00', 'close' => '22:00', 'closed' => false],
                        'wednesday' => ['open' => '10:00', 'close' => '22:00', 'closed' => false],
                        'thursday'  => ['open' => '10:00', 'close' => '22:00', 'closed' => false],
                        'friday'    => ['open' => '10:00', 'close' => '23:00', 'closed' => false],
                        'saturday'  => ['open' => '11:00', 'close' => '23:00', 'closed' => false],
                        'sunday'    => ['open' => '11:00', 'close' => '21:00', 'closed' => false],
                    ]),
                ]
            );

            if ($plan) {
                $subscription = Subscription::create([
                    'vendor_id' => $vendor->id,
                    'plan_id' => $plan->id,
                    'status' => $subStatus,
                    'billing_cycle' => $billingCycle,
                    'start_date' => now()->subMonths(8),
                    'next_billing_date' => $subStatus === 'expired' ? now()->subDays(4) : now()->addDays(15),
                    'auto_renew' => $subStatus !== 'expired',
                ]);

                $this->createInvoicesForSubscription($subscription, $plan, $billingCycle);
                $this->createEventsForSubscription($subscription, $plan, $subStatus);
            }

            $this->createActivities($vendor);
        }

        // Add pending change request for Bella Italia
        $bellaItalia = Vendor::where('slug', 'bella-italia')->first();
        if ($bellaItalia) {
            VendorRequestChange::create([
                'vendor_id' => $bellaItalia->id,
                'restaurant_name' => 'La Bella Vistag',
                'business_registration_number' => 'FN 123456at',
                'vat_number' => 'ATU12345678t',
                'address' => 'Kärntner Straße 1, 1010 Wien, Agustria',
                'vendor_notes' => 'We recently updated our legal entity name and registration details with the Austrian authorities. Please approve these changes to ensure our invoices are compliant.',
                'status' => false,
            ]);
        }
    }

    private function createInvoicesForSubscription(Subscription $subscription, SubscriptionPlan $plan, string $billingCycle): void
    {
        $price = $billingCycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price;
        $months = min(4, 8); // last 4 months of 8-month subscription

        for ($i = $months - 1; $i >= 0; $i--) {
            $billingStart = now()->subMonths($i)->startOfMonth();
            $billingEnd = $billingStart->copy()->endOfMonth();
            $isPaid = $i > 0; // current month unpaid for failed/overdue vendors
            $invoiceNum = 'INV-' . $subscription->id . '-' . $billingStart->format('Y-m');

            if ($subscription->status === 'trial' && $i > 0) {
                continue; // trial vendors only have current month
            }

            $invoice = Invoice::create([
                'subscription_id' => $subscription->id,
                'invoice_number' => $invoiceNum,
                'amount' => $price ?? 49.99,
                'currency' => 'EUR',
                'status' => $isPaid ? 'paid' : ($subscription->status === 'trial' ? 'pending' : 'unpaid'),
                'billing_period_start' => $billingStart,
                'billing_period_end' => $billingEnd,
                'due_date' => $billingStart->copy()->addDays(15),
                'paid_at' => $isPaid ? $billingStart->copy()->addDays(rand(3, 10)) : null,
            ]);

            if ($isPaid) {
                Payment::create([
                    'invoice_id' => $invoice->id,
                    'payment_provider' => 'stripe',
                    'provider_transaction_id' => 'pi_' . \Illuminate\Support\Str::random(16),
                    'amount' => $price ?? 49.99,
                    'currency' => 'EUR',
                    'status' => 'completed',
                    'payment_method' => 'card',
                ]);
            }
        }
    }

    private function createEventsForSubscription(Subscription $subscription, SubscriptionPlan $plan, string $subStatus): void
    {
        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'event_type' => 'created',
            'new_plan_id' => $plan->id,
            'metadata' => ['source' => 'seeder'],
            'created_at' => now()->subMonths(8),
            'updated_at' => now()->subMonths(8),
        ]);

        if ($plan->name === 'Premium') {
            $standard = SubscriptionPlan::where('name', 'Standard')->first();
            if ($standard) {
                SubscriptionEvent::create([
                    'subscription_id' => $subscription->id,
                    'event_type' => 'upgrade',
                    'previous_plan_id' => $standard->id,
                    'new_plan_id' => $plan->id,
                    'metadata' => ['source' => 'seeder'],
                    'created_at' => now()->subMonths(3),
                    'updated_at' => now()->subMonths(3),
                ]);
            }
        }
    }

    private function createActivities(Vendor $vendor): void
    {
        $activities = [
            ['event_type' => 'payment', 'title' => 'Payment Processed', 'description' => 'Monthly subscription payment', 'color' => 'bg-green-600', 'actor' => 'System', 'ago' => 2],
            ['event_type' => 'menu', 'title' => 'Menu Updated', 'description' => 'Items added or modified', 'color' => 'bg-blue-600', 'actor' => 'Vendor: Self-service', 'ago' => 24],
            ['event_type' => 'order', 'title' => 'Order Completed', 'description' => 'Order fulfilled', 'color' => 'bg-gray-600', 'actor' => 'System', 'ago' => 5],
        ];

        if ($vendor->payment_status === 'failed') {
            array_unshift($activities, [
                'event_type' => 'payment_failed',
                'title' => 'Payment Failed',
                'description' => 'Card declined - insufficient funds',
                'color' => 'bg-red-600',
                'actor' => 'Admin: System',
                'ago' => 2,
            ]);
        }

        if ($vendor->slug === 'bella-italia' || $vendor->slug === 'burger-palace') {
            $activities[] = [
                'event_type' => 'subscription',
                'title' => 'Subscription Upgraded',
                'description' => 'Standard → Premium',
                'color' => 'bg-purple-600',
                'actor' => 'Admin: Sarah Chen',
                'ago' => 2160, // ~3 months
            ];
        }

        foreach ($activities as $activity) {
            $ago = $activity['ago'];
            unset($activity['ago']);

            VendorActivity::create(array_merge($activity, [
                'vendor_id' => $vendor->id,
                'created_at' => now()->subHours($ago),
                'updated_at' => now()->subHours($ago),
            ]));
        }
    }
    
}
