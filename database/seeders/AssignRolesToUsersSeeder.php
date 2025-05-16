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
        $adminUser = User::where('email', 'admin@example.com')->first();
        if ($adminUser) {   
            $adminUser->assignRole('admin');
        }

        // Example: create and assign a manager
        $managerUser = User::firstOrCreate([
            'email' => 'manager@example.com',
        ], [
            'username' => 'manager',
            'name' => 'Manager User',
            'password' => bcrypt('password'),
        ]);
        $managerUser->assignRole('manager');

        // Example: create and assign a customer
        $customerUser = User::firstOrCreate([
            'email' => 'customer@example.com',
        ], [
            'username' => 'customer',
            'name' => 'Customer User',
            'password' => bcrypt('password'),
        ]);
        $customerUser->assignRole('customer');
    }
}
