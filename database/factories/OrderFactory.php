<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_public_id' => 'ord_' . Str::random(16),
            'customer_id'     => Customer::factory(),
            'vendor_id'       => Vendor::factory(),
            'status'          => 'completed',
            'items_count'     => $this->faker->numberBetween(1, 5),
            'items'           => [['name' => 'Test Item', 'qty' => 1, 'price' => 10.00]],
            'amount'          => $this->faker->randomFloat(2, 5, 100),
            'currency'        => 'EUR',
            'payment_method'  => 'card',
            'order_type'      => $this->faker->randomElement(['dine_in', 'takeaway']),
        ];
    }
}
