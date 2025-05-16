<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions (add more as needed)
        $permissions = [
            'manage vehicles',
            'manage bookings',
            'view reports',
            'leave feedback',
        ];
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Create roles and assign permissions
        $admin = Role::firstOrCreate(['name' => 'admin']);
        $admin->givePermissionTo(['manage vehicles', 'manage bookings', 'view reports']);

        $manager = Role::firstOrCreate(['name' => 'manager']);
        $manager->givePermissionTo(['view reports']);

        $customer = Role::firstOrCreate(['name' => 'customer']);
        $customer->givePermissionTo(['leave feedback']);
    }
}
