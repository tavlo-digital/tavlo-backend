<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo customer accounts. All logins use the password "password".
 *
 * `orders_count` / `total_spend` / `loyalty_points` are recalculated from the
 * real rows by CustomerDataSeeder once its orders exist.
 */
class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'customer_public_id' => 'C-1024',
                'first_name' => 'Anna',
                'last_name' => 'Müller',
                'phone' => '+43 1 111 2222',
                'email' => 'anna.mueller@example.com',
                'gender' => 'female',
                'date_of_birth' => '1992-04-18',
                'address' => 'Neubaugasse 14, 1070 Vienna',
                'account_type' => 'registered',
                'risk_level' => 'none',
                'risk_tooltip' => null,
                'registration_source' => 'qr_code',
                'phone_verified' => true,
                'last_active_at' => now()->subHours(2),
                'email_verified_at' => now()->subMonths(6),
            ],
            [
                'customer_public_id' => 'C-2048',
                'first_name' => 'Max',
                'last_name' => 'Fischer',
                'phone' => '+43 1 333 4444',
                'email' => 'max.fischer@example.com',
                'gender' => 'male',
                'date_of_birth' => '1987-11-02',
                'address' => 'Landstraßer Hauptstraße 5, 1030 Vienna',
                'account_type' => 'registered',
                'risk_level' => 'none',
                'risk_tooltip' => null,
                'registration_source' => 'web',
                'phone_verified' => true,
                'last_active_at' => now()->subDay(),
                'email_verified_at' => now()->subMonths(10),
            ],
            [
                'customer_public_id' => 'C-3072',
                'first_name' => 'Sophie',
                'last_name' => 'Wagner',
                'phone' => '+43 1 555 6666',
                'email' => 'sophie.wagner@example.com',
                'gender' => 'female',
                'date_of_birth' => '1995-07-25',
                'address' => 'Herrengasse 9, 8010 Graz',
                'account_type' => 'registered',
                'risk_level' => 'red',
                'risk_tooltip' => 'Multiple failed payment attempts, dispute filed',
                'registration_source' => 'web',
                'phone_verified' => false,
                'last_active_at' => now()->subHours(3),
                'email_verified_at' => now()->subMonths(2),
            ],
            [
                'customer_public_id' => 'C-4096',
                'first_name' => 'Guest',
                'last_name' => 'User',
                'phone' => '+43 1 777 8888',
                'email' => 'guest4096@example.com',
                'gender' => null,
                'date_of_birth' => null,
                'address' => null,
                'account_type' => 'guest',
                'risk_level' => 'none',
                'risk_tooltip' => null,
                'registration_source' => 'qr_code',
                'phone_verified' => false,
                'last_active_at' => now()->subHours(5),
                'email_verified_at' => null,
            ],
            [
                'customer_public_id' => 'C-5120',
                'first_name' => 'Thomas',
                'last_name' => 'Bauer',
                'phone' => '+43 1 999 0000',
                'email' => 'thomas.bauer@example.com',
                'gender' => 'male',
                'date_of_birth' => '1990-01-09',
                'address' => 'Getreidegasse 21, 5020 Salzburg',
                'account_type' => 'registered',
                'risk_level' => 'orange',
                'risk_tooltip' => '5 refund requests in last 30 days',
                'registration_source' => 'mobile_app',
                'phone_verified' => true,
                'last_active_at' => now()->subHours(12),
                'email_verified_at' => now()->subMonths(4),
            ],
        ];

        foreach ($customers as $data) {
            Customer::updateOrCreate(
                ['customer_public_id' => $data['customer_public_id']],
                array_merge($data, ['password' => Hash::make('password')])
            );
        }
    }
}
