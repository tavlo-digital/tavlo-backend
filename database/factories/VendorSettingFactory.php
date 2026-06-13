<?php

namespace Database\Factories;

use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VendorSetting>
 */
class VendorSettingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vendor_id'                => Vendor::factory(),
            'is_live_and_discoverable' => true,
            'description'              => $this->faker->sentence(),
            'enable_reservations'      => true,
            'loyalty_enabled'          => false,
        ];
    }
}
