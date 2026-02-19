<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::firstOrCreate(
            ['email' => 'admin@comfort-crm.com'],
            [
                'name'      => 'Admin User',
                'password'  => Hash::make('password'),
                'role'      => 'admin',
                'phone'     => '+1000000001',
                'is_active' => true,
            ]
        );

        // Manager
        User::firstOrCreate(
            ['email' => 'manager@comfort-crm.com'],
            [
                'name'      => 'Manager User',
                'password'  => Hash::make('password'),
                'role'      => 'manager',
                'phone'     => '+1000000002',
                'is_active' => true,
            ]
        );

        // Agent 1
        User::firstOrCreate(
            ['email' => 'agent1@comfort-crm.com'],
            [
                'name'      => 'Agent One',
                'password'  => Hash::make('password'),
                'role'      => 'agent',
                'phone'     => '+1000000003',
                'is_active' => true,
            ]
        );

        // Agent 2
        User::firstOrCreate(
            ['email' => 'agent2@comfort-crm.com'],
            [
                'name'      => 'Agent Two',
                'password'  => Hash::make('password'),
                'role'      => 'agent',
                'phone'     => '+1000000004',
                'is_active' => true,
            ]
        );

        $this->command->info('Default users created successfully.');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin',   'admin@comfort-crm.com',   'password'],
                ['Manager', 'manager@comfort-crm.com', 'password'],
                ['Agent',   'agent1@comfort-crm.com',  'password'],
                ['Agent',   'agent2@comfort-crm.com',  'password'],
            ]
        );
    }
}
