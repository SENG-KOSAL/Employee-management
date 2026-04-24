<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->where('slug', 'demo-company')->first();
        $companyId = $company?->id;

        Employee::factory()
            ->count(50)
            ->state(function () use ($companyId) {
                return $companyId ? ['company_id' => $companyId] : [];
            })
            ->create()
            ->each(function (Employee $employee) {
                EmployeeDocument::factory()->create([
                    'employee_id' => $employee->id,
                    'company_id' => $employee->company_id,
                ]);
            });
    }
}

