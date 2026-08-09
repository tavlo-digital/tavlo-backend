<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\RestaurantTable;
use App\Models\Subscription;
use App\Models\SubscriptionEvent;
use App\Models\SubscriptionPlan;
use App\Models\TeamMember;
use App\Models\Vendor;
use App\Models\VendorActivity;
use App\Models\VendorRequestChange;
use App\Models\VendorSetting;
use App\Models\VendorTakeawayQr;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo vendors with everything an operating restaurant needs:
 * settings, tables + QR tokens, staff accounts, billing history and activity.
 *
 * All logins use the password "password".
 */
class VendorSeeder extends Seeder
{
    /** Austrian SaaS VAT applied to subscription invoices. */
    private const SUBSCRIPTION_VAT_RATE = 20.0;

    /**
     * The vendor_public_ids owned by this seeder.
     * Only these vendors (and their related rows) are wiped before re-seeding.
     * Vendors created via the app are left untouched.
     */
    private const SEEDED_VENDOR_PUBLIC_IDS = [
        'VID-8492', // Bella Italia
        'VID-2847', // Pizza Express
        'VID-9471', // Sakura Sushi
        'VID-1234', // Green Bowl Cafe
        'VID-5678', // Burger Palace
    ];

    public function run(): void
    {
        $this->purgeSeededVendors();

        $plans = SubscriptionPlan::whereIn('name', ['Basic', 'Standard', 'Premium'])
            ->get()
            ->keyBy('name');

        foreach ($this->vendorDefinitions() as $definition) {
            $vendor = Vendor::create(array_merge($definition['vendor'], [
                'password' => Hash::make('password'),
                'email_verified_at' => now()->subMonths(8),
            ]));

            $this->createSettings($vendor, $definition['settings']);
            $this->createTables($vendor, $definition['table_count']);
            $this->createTakeawayQr($vendor);
            $this->createTeam($vendor, $definition['team']);

            $plan = $plans->get($definition['billing']['plan']);

            if ($plan) {
                $this->createBilling($vendor, $plan, $definition['billing']);
            }

            $this->createActivities($vendor);
        }

        $this->createPendingLegalChange();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function vendorDefinitions(): array
    {
        return [
            [
                'vendor' => [
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
                    'latitude' => 48.2050,
                    'longitude' => 16.3710,
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
                ],
                'settings' => [
                    'description' => 'Family-run trattoria in the heart of Vienna serving Roman classics since 1998.',
                    'company_type' => 'GmbH',
                    'supported_languages' => ['en', 'de', 'it'],
                    'dashboard_language' => 'de',
                    'service_fee_rate' => 2.5,
                    'loyalty_enabled' => true,
                    'enable_reservations' => true,
                    'inventory_tracking_enabled' => true,
                    'years_of_experience' => 27,
                    'signature_recipes_count' => 12,
                    'happy_customers_count' => 48000,
                    'restaurant_features' => ['wifi', 'outdoor_seating', 'wheelchair_accessible', 'vegan_options'],
                    'primary_color' => '#1f2937',
                    'accent_color' => '#c2410c',
                ],
                'table_count' => 12,
                'team' => [
                    ['name' => 'Luca Rossi', 'email' => 'luca.rossi@bellaitalia.at', 'role' => 'waiter', 'status' => 'active'],
                    ['name' => 'Marco Bianchi', 'email' => 'marco.bianchi@bellaitalia.at', 'role' => 'kitchen', 'status' => 'active'],
                    ['name' => 'Giulia Conti', 'email' => 'giulia.conti@bellaitalia.at', 'role' => 'waiter', 'status' => 'invited'],
                ],
                'billing' => ['plan' => 'Premium', 'status' => 'active', 'cycle' => 'monthly'],
            ],
            [
                'vendor' => [
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
                    'latitude' => 47.7998,
                    'longitude' => 13.0439,
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
                    'last_activity_at' => now()->subDay(),
                ],
                'settings' => [
                    'description' => 'Fast Neapolitan pizza and takeaway in Salzburg old town.',
                    'company_type' => 'KG',
                    'supported_languages' => ['en', 'de'],
                    'dashboard_language' => 'de',
                    'service_fee_rate' => 0,
                    'loyalty_enabled' => false,
                    'enable_reservations' => false,
                    'inventory_tracking_enabled' => false,
                    'years_of_experience' => 9,
                    'signature_recipes_count' => 6,
                    'happy_customers_count' => 15000,
                    'restaurant_features' => ['wifi', 'takeaway'],
                    'primary_color' => '#111827',
                    'accent_color' => '#dc2626',
                ],
                'table_count' => 8,
                'team' => [
                    ['name' => 'Jonas Huber', 'email' => 'jonas.huber@pizzaexpress.at', 'role' => 'waiter', 'status' => 'active'],
                    ['name' => 'Nina Berger', 'email' => 'nina.berger@pizzaexpress.at', 'role' => 'kitchen', 'status' => 'active'],
                ],
                'billing' => ['plan' => 'Standard', 'status' => 'expired', 'cycle' => 'monthly'],
            ],
            [
                'vendor' => [
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
                    'latitude' => 48.1983,
                    'longitude' => 16.3520,
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
                ],
                'settings' => [
                    'description' => 'Omakase-style sushi counter with daily deliveries from the Adriatic.',
                    'company_type' => 'e.U.',
                    'supported_languages' => ['en', 'de', 'ja'],
                    'dashboard_language' => 'en',
                    'service_fee_rate' => 3.0,
                    'loyalty_enabled' => false,
                    'enable_reservations' => true,
                    'inventory_tracking_enabled' => false,
                    'years_of_experience' => 4,
                    'signature_recipes_count' => 8,
                    'happy_customers_count' => 3200,
                    'restaurant_features' => ['wifi', 'reservations'],
                    'primary_color' => '#0f172a',
                    'accent_color' => '#e11d48',
                ],
                'table_count' => 6,
                'team' => [
                    ['name' => 'Kenji Sato', 'email' => 'kenji.sato@sakurasushi.at', 'role' => 'kitchen', 'status' => 'active'],
                ],
                'billing' => ['plan' => 'Basic', 'status' => 'trial', 'cycle' => 'monthly'],
            ],
            [
                'vendor' => [
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
                    'latitude' => 47.0700,
                    'longitude' => 15.4382,
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
                ],
                'settings' => [
                    'description' => 'Plant-forward bowls, cold-pressed juices and specialty coffee.',
                    'company_type' => 'GmbH',
                    'supported_languages' => ['en', 'de'],
                    'dashboard_language' => 'de',
                    'service_fee_rate' => 0,
                    'loyalty_enabled' => true,
                    'enable_reservations' => false,
                    'inventory_tracking_enabled' => true,
                    'years_of_experience' => 6,
                    'signature_recipes_count' => 10,
                    'happy_customers_count' => 21000,
                    'restaurant_features' => ['wifi', 'vegan_options', 'takeaway'],
                    'primary_color' => '#14532d',
                    'accent_color' => '#65a30d',
                ],
                'table_count' => 10,
                'team' => [
                    ['name' => 'Lena Gruber', 'email' => 'lena.gruber@greenbowlcafe.at', 'role' => 'waiter', 'status' => 'active'],
                ],
                'billing' => ['plan' => 'Basic', 'status' => 'active', 'cycle' => 'monthly'],
            ],
            [
                'vendor' => [
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
                    'latitude' => 48.2160,
                    'longitude' => 16.3855,
                    'phone' => '+43 1 555 7890',
                    'email' => 'hello@burgerpalace.at',
                    'status' => 'active',
                    'live_status' => 'live',
                    'risk_level' => 'none',
                    'orders_count' => 892,
                    'revenue_total' => 34820.00,
                    'users_used' => 12,
                    'payment_status' => 'paid',
                    'payment_last_success' => now()->subDay(),
                    'payment_failures_24h' => 0,
                    'last_activity_at' => now()->subHour(),
                ],
                'settings' => [
                    'description' => 'Smash burgers, hand-cut fries and craft beer near the Prater.',
                    'company_type' => 'OG',
                    'supported_languages' => ['en', 'de'],
                    'dashboard_language' => 'en',
                    'service_fee_rate' => 2.0,
                    'loyalty_enabled' => true,
                    'enable_reservations' => true,
                    'inventory_tracking_enabled' => true,
                    'years_of_experience' => 11,
                    'signature_recipes_count' => 9,
                    'happy_customers_count' => 76000,
                    'restaurant_features' => ['wifi', 'outdoor_seating', 'takeaway', 'family_friendly'],
                    'primary_color' => '#1c1917',
                    'accent_color' => '#f59e0b',
                ],
                'table_count' => 16,
                'team' => [
                    ['name' => 'Tobias Wolf', 'email' => 'tobias.wolf@burgerpalace.at', 'role' => 'waiter', 'status' => 'active'],
                    ['name' => 'Sara Klein', 'email' => 'sara.klein@burgerpalace.at', 'role' => 'kitchen', 'status' => 'active'],
                ],
                'billing' => ['plan' => 'Premium', 'status' => 'active', 'cycle' => 'yearly'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createSettings(Vendor $vendor, array $overrides): void
    {
        $defaults = [
            'is_live_and_discoverable' => true,
            'accept_on_site' => true,
            'stripe_enabled' => false,
            'business_hours' => $this->businessHours(),
            'invoice_prefix' => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $vendor->slug), 0, 3)) ?: 'INV',
            'next_invoice_number' => 1001,
            'auto_generate_receipts' => true,
            'enable_shared_basket' => true,
            'max_guests_per_table' => 10,
            'auto_accept_orders' => false,
            'estimated_prep_time' => 20,
            'allow_guest_ordering' => true,
            'enable_reviews' => true,
            'enable_menu_reviews' => true,
            'allow_anonymous_reviews' => true,
            'points_per_euro' => 20,
            'minimum_redemption_points' => 100,
            'point_value' => 0.10,
            'redemption_rate' => 0.05,
            'points_expiry_days' => 365,
            'notification_email' => $vendor->email,
            'menu_theme' => 'classic',
            'menu_layout' => 'grid',
            'date_format' => 'DD.MM.YYYY',
            'time_format' => '24h',
            'show_in_top_customers' => true,
            'show_phone_public' => true,
            'show_email_public' => false,
            'show_website_public' => $vendor->website !== null,
        ];

        VendorSetting::updateOrCreate(
            ['vendor_id' => $vendor->id],
            array_merge($defaults, $overrides)
        );
    }

    /**
     * @return array<string, array{open: string, close: string, closed: bool}>
     */
    private function businessHours(): array
    {
        return [
            'monday' => ['open' => '10:00', 'close' => '22:00', 'closed' => false],
            'tuesday' => ['open' => '10:00', 'close' => '22:00', 'closed' => false],
            'wednesday' => ['open' => '10:00', 'close' => '22:00', 'closed' => false],
            'thursday' => ['open' => '10:00', 'close' => '22:00', 'closed' => false],
            'friday' => ['open' => '10:00', 'close' => '23:00', 'closed' => false],
            'saturday' => ['open' => '11:00', 'close' => '23:00', 'closed' => false],
            'sunday' => ['open' => '11:00', 'close' => '21:00', 'closed' => true],
        ];
    }

    /**
     * QR tokens are derived from the vendor slug and table number so that a
     * re-seed keeps every previously printed QR code pointing at the same table.
     */
    private function createTables(Vendor $vendor, int $count): void
    {
        for ($number = 1; $number <= $count; $number++) {
            RestaurantTable::updateOrCreate(
                ['vendor_id' => $vendor->id, 'number' => $number],
                [
                    'name' => 'Table '.$number,
                    'qr_token' => $this->stableUuid("table:{$vendor->slug}:{$number}"),
                    'is_active' => true,
                    'qr_created_at' => now()->subMonths(6),
                ]
            );
        }

        $vendor->vendorSetting?->update(['number_of_tables' => $count]);
    }

    private function createTakeawayQr(Vendor $vendor): void
    {
        VendorTakeawayQr::updateOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'qr_token' => $this->stableUuid("takeaway:{$vendor->slug}"),
                'last_regenerated_at' => now()->subMonths(6),
            ]
        );
    }

    /**
     * @param  array<int, array<string, string>>  $members
     */
    private function createTeam(Vendor $vendor, array $members): void
    {
        foreach ($members as $member) {
            $isActive = $member['status'] === 'active';

            TeamMember::updateOrCreate(
                ['email' => $member['email']],
                [
                    'vendor_id' => $vendor->id,
                    'name' => $member['name'],
                    'role' => $member['role'],
                    'permissions' => TeamMember::defaultPermissions($member['role']),
                    'status' => $member['status'],
                    'password' => $isActive ? Hash::make('password') : null,
                    'invitation_token' => $isActive ? null : TeamMember::generateInvitationToken(),
                    'invited_at' => now()->subMonths(5),
                    'joined_at' => $isActive ? now()->subMonths(5)->addDay() : null,
                ]
            );
        }
    }

    /**
     * @param  array{plan: string, status: string, cycle: string}  $billing
     */
    private function createBilling(Vendor $vendor, SubscriptionPlan $plan, array $billing): void
    {
        $subscription = Subscription::create([
            'vendor_id' => $vendor->id,
            'plan_id' => $plan->id,
            'status' => $billing['status'],
            'billing_cycle' => $billing['cycle'],
            'start_date' => now()->subMonths(8),
            'next_billing_date' => $billing['status'] === 'expired'
                ? now()->subDays(4)
                : now()->addDays(15),
            'auto_renew' => $billing['status'] !== 'expired',
        ]);

        PaymentMethod::updateOrCreate(
            ['vendor_id' => $vendor->id, 'stripe_payment_method_id' => 'pm_seed_'.$vendor->id],
            [
                'card_brand' => 'visa',
                'last4' => str_pad((string) (4000 + $vendor->id), 4, '0', STR_PAD_LEFT),
                'exp_month' => '11',
                'exp_year' => (string) now()->addYears(3)->year,
                'is_default' => true,
                'billing_email' => $vendor->email,
            ]
        );

        $this->createInvoices($subscription, $plan, $billing);
        $this->createSubscriptionEvents($subscription, $plan);
    }

    /**
     * `amount` is the gross total charged, `vat` the tax portion of it —
     * matching what the Stripe invoice webhook writes.
     *
     * @param  array{plan: string, status: string, cycle: string}  $billing
     */
    private function createInvoices(Subscription $subscription, SubscriptionPlan $plan, array $billing): void
    {
        $net = (float) ($billing['cycle'] === 'yearly' ? $plan->yearly_price : $plan->monthly_price);
        $gross = round($net * (1 + self::SUBSCRIPTION_VAT_RATE / 100), 2);
        $vat = round($gross - $net, 2);

        // Trial subscriptions have not been billed yet.
        $periods = $billing['status'] === 'trial' ? 1 : 4;

        for ($monthsAgo = $periods - 1; $monthsAgo >= 0; $monthsAgo--) {
            $periodStart = now()->subMonths($monthsAgo)->startOfMonth();
            $isCurrentPeriod = $monthsAgo === 0;

            $status = match (true) {
                ! $isCurrentPeriod => 'paid',
                $billing['status'] === 'trial' => 'pending',
                $billing['status'] === 'expired' => 'unpaid',
                default => 'paid',
            };

            $invoice = Invoice::create([
                'subscription_id' => $subscription->id,
                'invoice_number' => sprintf('INV-%d-%s', $subscription->id, $periodStart->format('Y-m')),
                'amount' => $gross,
                'vat' => $vat,
                'currency' => $plan->currency ?? 'EUR',
                'status' => $status,
                'billing_period_start' => $periodStart,
                'billing_period_end' => $periodStart->copy()->endOfMonth(),
                'due_date' => $periodStart->copy()->addDays(15),
                'paid_at' => $status === 'paid' ? $periodStart->copy()->addDays(3) : null,
            ]);

            if ($status !== 'paid') {
                continue;
            }

            Payment::create([
                'invoice_id' => $invoice->id,
                'payment_provider' => 'stripe',
                'provider_transaction_id' => 'pi_'.Str::random(24),
                'amount' => $gross,
                'currency' => $invoice->currency,
                'status' => 'completed',
                'payment_method' => 'card',
            ]);
        }
    }

    private function createSubscriptionEvents(Subscription $subscription, SubscriptionPlan $plan): void
    {
        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'event_type' => 'created',
            'new_plan_id' => $plan->id,
            'metadata' => ['source' => 'seeder'],
            'created_at' => now()->subMonths(8),
            'updated_at' => now()->subMonths(8),
        ]);

        // Premium vendors got there by upgrading from Standard three months ago.
        if ($plan->name !== 'Premium' || ! $plan->parent_plan_id) {
            return;
        }

        SubscriptionEvent::create([
            'subscription_id' => $subscription->id,
            'event_type' => 'upgrade',
            'previous_plan_id' => $plan->parent_plan_id,
            'new_plan_id' => $plan->id,
            'metadata' => ['source' => 'seeder'],
            'created_at' => now()->subMonths(3),
            'updated_at' => now()->subMonths(3),
        ]);
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

        if (in_array($vendor->slug, ['bella-italia', 'burger-palace'], true)) {
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

    /**
     * One vendor waits on an admin decision so the approval queue is never empty.
     */
    private function createPendingLegalChange(): void
    {
        $vendor = Vendor::where('slug', 'bella-italia')->first();

        if (! $vendor) {
            return;
        }

        VendorRequestChange::create([
            'vendor_id' => $vendor->id,
            'restaurant_name' => 'La Bella Vista GmbH',
            'legal_entity_name' => 'La Bella Vista Gastronomy GmbH',
            'business_registration_number' => 'FN 123456a',
            'vat_number' => 'ATU12345678',
            'company_type' => 'GmbH',
            'country' => 'Austria',
            'city' => 'Vienna',
            'address' => 'Kärntner Straße 1, 1010 Wien',
            'vendor_notes' => 'We recently updated our legal entity name and registration details with the Austrian authorities. Please approve these changes to ensure our invoices are compliant.',
            'status' => 'pending',
        ]);
    }

    /**
     * Delete only the vendors this seeder owns.
     *
     * Every vendor-scoped table (settings, tables, sessions, orders, menu,
     * billing, …) is wired with ON DELETE CASCADE, so removing the vendor rows
     * removes their whole object graph. Vendors created through the app are
     * NOT touched.
     */
    private function purgeSeededVendors(): void
    {
        DB::table('vendors')
            ->whereIn('vendor_public_id', self::SEEDED_VENDOR_PUBLIC_IDS)
            ->delete();
    }

    /**
     * A deterministic, RFC-4122-shaped UUID for a given seed string.
     */
    private function stableUuid(string $seed): string
    {
        $hash = md5($seed);

        return sprintf(
            '%08s-%04s-5%03s-%04x-%12s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            (hexdec(substr($hash, 16, 4)) & 0x3FFF) | 0x8000,
            substr($hash, 20, 12)
        );
    }
}
