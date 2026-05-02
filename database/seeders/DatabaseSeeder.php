<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(FeatureSeeder::class);
        $this->call(SubscriptionPlanSeeder::class);
        $this->call(VendorSeeder::class);
        $this->call(AllergenAndTagSeeder::class);
        $this->call(CustomerSeeder::class);
        $this->call(CustomerDataSeeder::class);
        $this->call(MenuSeeder::class);
        $this->call(TableSessionFlowSeeder::class);

        $adminRole = Role::where('name', 'admin')->first();

        User::firstOrCreate(
            ['email' => 'admin@neltar.com'],
            [
                'name'              => 'Admin',
                'password'          => Hash::make('password'),
                'role_id'           => $adminRole?->id,
                'email_verified_at' => now(),
            ]
        );
    }
}

