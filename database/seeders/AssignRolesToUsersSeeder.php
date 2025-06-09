<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;

class AssignRolesToUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Assign roles to example users (customize as needed)
        $adminUser = User::firstOrCreate([
            'email' => 'rimuruuu021@gmail.com',
        ], [
            'username' => 'admin',
            'name' => 'Admin User',
            'address' => '123 Admin St, City',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $adminUser->assignRole('admin');

        // Example: create and assign a manager
        $managerUser = User::firstOrCreate([
            'email' => 'rimuruuu022@gmail.com',
        ], [
            'username' => 'manager',
            'name' => 'Manager User',
            'address' => '456 Manager Ave, City',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $managerUser->assignRole('manager');

        // Example: create and assign a customer
        $customerUser = User::firstOrCreate([
            'email' => 'mascarinas033@gmail.com',
        ], [
            'username' => 'customer',
            'name' => 'Customer User',
            'address' => '789 Customer Rd, City',
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);
        $customerUser->assignRole('customer');
    }
}
