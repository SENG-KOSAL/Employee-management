<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $now = now();

        $users = [
            [
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('Admin123'),
                'role' => 'admin',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'HR Manager',
                'email' => 'hr@example.com',
                'password' => Hash::make('password'),
                'role' => 'hr',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Line Manager',
                'email' => 'manager@example.com',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Employee User',
                'email' => 'employee@example.com',
                'password' => Hash::make('password'),
                'role' => 'employee',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        User::query()->upsert($users, ['email'], ['name', 'password', 'role', 'updated_at']);
    }
}
