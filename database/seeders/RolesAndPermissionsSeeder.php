<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Permissions
        $permissions = [
            // Users
            ['name' => 'users.view',   'label' => 'View Users',   'group' => 'Users'],
            ['name' => 'users.create', 'label' => 'Create Users', 'group' => 'Users'],
            ['name' => 'users.edit',   'label' => 'Edit Users',   'group' => 'Users'],
            ['name' => 'users.delete', 'label' => 'Delete Users', 'group' => 'Users'],

            // Settings
            ['name' => 'settings.view', 'label' => 'View Settings', 'group' => 'Settings'],
            ['name' => 'settings.edit', 'label' => 'Edit Settings', 'group' => 'Settings'],
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm['name']], $perm);
        }

        // Admin role — gets all permissions
        $admin = Role::firstOrCreate(['name' => 'admin'], ['label' => 'Admin']);
        $admin->permissions()->sync(Permission::pluck('id'));

        // Editor role
        $editor = Role::firstOrCreate(['name' => 'editor'], ['label' => 'Editor']);
        $editor->permissions()->sync(
            Permission::whereIn('name', ['users.view', 'settings.view'])->pluck('id')
        );

        // User role — no special permissions
        Role::firstOrCreate(['name' => 'user'], ['label' => 'User']);
    }
}
