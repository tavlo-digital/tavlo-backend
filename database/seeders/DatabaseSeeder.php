<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Full demo dataset.
 *
 * Order matters: reference data first, then vendors, then the menus that point
 * at both, then the customer history that consumes the menus.
 *
 * Every seeder is idempotent — `php artisan db:seed` can be re-run and will
 * rebuild the demo rows it owns without touching data created through the app.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Platform-wide reference data
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(ReferenceDataSeeder::class);
        $this->call(FeatureSeeder::class);
        $this->call(SubscriptionPlanSeeder::class);

        // Tenants and their catalogue
        $this->call(VendorSeeder::class);
        $this->call(MenuSeeder::class);

        // Customers and their history
        $this->call(CustomerSeeder::class);
        $this->call(CustomerDataSeeder::class);

        // Live traffic: one open table, one takeaway queue
        $this->call(TableSessionFlowSeeder::class);
        $this->call(OrderSeeder::class);

        $this->createAdminUser();
    }

    private function createAdminUser(): void
    {
        $adminRole = Role::where('name', 'admin')->first();

        // firstOrCreate, not updateOrCreate: never reset a password that has
        // already been changed on a running environment.
        User::firstOrCreate(
            ['email' => 'admin@neltar.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role_id' => $adminRole?->id,
                'email_verified_at' => now(),
            ]
        );
    }
}
