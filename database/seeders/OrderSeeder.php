<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $vendor   = Vendor::where('vendor_public_id', 'VID-8492')->first();
        $customer = Customer::first();

        if (! $vendor || ! $customer) {
            $this->command->warn('OrderSeeder: vendor or customer not found — run VendorSeeder and CustomerSeeder first.');
            return;
        }

        \DB::table('orders')->insertOrIgnore([
            'order_public_id'  => 'ORD-' . strtoupper(Str::random(8)),
            'order_number'     => 9001,
            'vendor_id'        => $vendor->id,
            'customer_id'      => $customer->id,
            'order_type'       => 'takeaway',
            'table_number'     => null,
            'status'           => 'confirmed',
            'amount'           => 25.90,
            'service_fee'      => 1.30,
            'vat_amount'       => 2.59,
            'currency'         => 'EUR',
            'payment_method'   => 'card',
            'payment_received' => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $this->command->info('OrderSeeder: 1 order seeded.');
    }
}
