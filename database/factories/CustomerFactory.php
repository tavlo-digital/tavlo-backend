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
            'customer_public_id' => 'cust_' . Str::random(16),
            'first_name'         => $this->faker->firstName(),
            'last_name'          => $this->faker->lastName(),
            'email'              => $this->faker->unique()->safeEmail(),
            'phone'              => $this->faker->unique()->phoneNumber(),
            'password'           => Hash::make('password'),
            'account_type'       => 'registered',
            'registration_source' => 'email',
        ];
    }

    public function social(string $provider = 'google'): static
    {
        return $this->state(fn () => [
            'social_provider'    => $provider,
            'social_provider_id' => Str::random(21),
            'registration_source' => $provider,
            'email_verified_at'  => now(),
        ]);
    }
}
