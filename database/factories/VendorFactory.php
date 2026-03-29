<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Vendor>
 */
class VendorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vendor_public_id'  => 'V-' . strtoupper(Str::random(8)),
            'slug'              => $this->faker->unique()->slug(2),
            'name'              => $this->faker->name(),
            'restaurant_name'   => $this->faker->company(),
            'email'             => $this->faker->unique()->safeEmail(),
            'password'          => Hash::make('password'),
            'country'           => 'Austria',
            'city'              => 'Vienna',
            'address'           => $this->faker->streetAddress(),
            'phone'             => $this->faker->phoneNumber(),
            'status'            => 'active',
            'live_status'       => 'online',
        ];
    }
}
