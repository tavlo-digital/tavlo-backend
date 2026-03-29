<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_public_id' => 'C-' . strtoupper(Str::random(8)),
            'name'               => $this->faker->name(),
            'email'              => $this->faker->unique()->safeEmail(),
            'phone'              => $this->faker->phoneNumber(),
            'password'           => Hash::make('password'),
            'account_type'       => 'guest',
        ];
    }
}
