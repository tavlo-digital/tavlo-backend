<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            [
                'customer_public_id' => 'C-1024',
                'name' => 'Anna Müller',
                'phone' => '+43 1 111 2222',
                'email' => 'anna.mueller@example.com',
                'password' => Hash::make('password'),
                'account_type' => 'registered',
                'risk_level' => 'none',
                'risk_tooltip' => null,
                'orders_count' => 47,
                'total_spend' => 1284.50,
                'loyalty_points' => 1285,
                'registration_source' => 'qr_code',
                'last_active_at' => now()->subHours(2),
                'email_verified_at' => now()->subMonths(6),
            ],
            [
                'customer_public_id' => 'C-2048',
                'name' => 'Max Fischer',
                'phone' => '+43 1 333 4444',
                'email' => 'max.fischer@example.com',
                'password' => Hash::make('password'),
                'account_type' => 'registered',
                'risk_level' => 'none',
                'risk_tooltip' => null,
                'orders_count' => 89,
                'total_spend' => 3456.20,
                'loyalty_points' => 3456,
                'registration_source' => 'web',
                'last_active_at' => now()->subDays(1),
                'email_verified_at' => now()->subMonths(10),
            ],
            [
                'customer_public_id' => 'C-3072',
                'name' => 'Sophie Wagner',
                'phone' => '+43 1 555 6666',
                'email' => 'sophie.wagner@example.com',
                'password' => Hash::make('password'),
                'account_type' => 'registered',
                'risk_level' => 'red',
                'risk_tooltip' => 'Multiple failed payment attempts, dispute filed',
                'orders_count' => 3,
                'total_spend' => 450.00,
                'loyalty_points' => 450,
                'registration_source' => 'web',
                'last_active_at' => now()->subHours(3),
                'email_verified_at' => now()->subMonths(2),
            ],
            [
                'customer_public_id' => 'C-4096',
                'name' => 'Guest User',
                'phone' => '+43 1 777 8888',
                'email' => 'guest4096@example.com',
                'password' => Hash::make('password'),
                'account_type' => 'guest',
                'risk_level' => 'none',
                'risk_tooltip' => null,
                'orders_count' => 1,
                'total_spend' => 28.90,
                'loyalty_points' => 29,
                'registration_source' => 'qr_code',
                'last_active_at' => now()->subHours(5),
                'email_verified_at' => null,
            ],
            [
                'customer_public_id' => 'C-5120',
                'name' => 'Thomas Bauer',
                'phone' => '+43 1 999 0000',
                'email' => 'thomas.bauer@example.com',
                'password' => Hash::make('password'),
                'account_type' => 'registered',
                'risk_level' => 'orange',
                'risk_tooltip' => '5 refund requests in last 30 days',
                'orders_count' => 12,
                'total_spend' => 680.00,
                'loyalty_points' => 680,
                'registration_source' => 'mobile_app',
                'last_active_at' => now()->subHours(12),
                'email_verified_at' => now()->subMonths(4),
            ],
        ];

        foreach ($customers as $data) {
            Customer::create($data);
        }
    }
}
