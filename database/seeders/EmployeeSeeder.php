<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        Employee::factory()
            ->count(50)
            ->create()
            ->each(function (Employee $employee) {
                EmployeeDocument::factory()->create([
                    'employee_id' => $employee->id,
                ]);
            });
    }
}