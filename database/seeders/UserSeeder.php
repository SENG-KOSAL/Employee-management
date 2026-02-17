<?php

namespace Database\Seeders;

use App\Models\Company;
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

        $company = Company::query()->updateOrCreate(
            ['slug' => 'demo-company'],
            [
                'name' => 'Demo Company',
                'status' => 'active',
                'modules_enabled' => [
                    'employees' => true,
                    'attendance' => true,
                    'overtime' => true,
                    'payroll' => true,
                ],
                'settings' => [],
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );

        $users = [
            [
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'email' => 'superadmin@example.com',
                'password' => Hash::make('SuperAdmin123'),
                'role' => 'super_admin',
                'company_id' => null,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Client Admin',
                'username' => 'companyadmin',
                'email' => 'companyadmin@example.com',
                'password' => Hash::make('CompanyAdmin123'),
                'role' => 'company_admin',
                'company_id' => $company->id,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Admin',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'password' => Hash::make('Admin123'),
                'role' => 'admin',
                'company_id' => $company->id,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'HR Manager',
                'username' => 'hrmanager',
                'email' => 'hr@example.com',
                'password' => Hash::make('password'),
                'role' => 'hr',
                'company_id' => $company->id,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Line Manager',
                'username' => 'manager',
                'email' => 'manager@example.com',
                'password' => Hash::make('password'),
                'role' => 'manager',
                'company_id' => $company->id,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Employee User',
                'username' => 'employee',
                'email' => 'employee@example.com',
                'password' => Hash::make('password'),
                'role' => 'employee',
                'company_id' => $company->id,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        User::query()->upsert($users, ['email'], ['name', 'username', 'password', 'role', 'company_id', 'status', 'updated_at']);
    }
}
