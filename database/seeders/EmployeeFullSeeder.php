<?php

// namespace Database\Seeders;

// use App\Models\Department;
// use App\Models\Employee;
// use App\Models\EmployeeBenefit;
// use App\Models\LeaveAllocation;
// use App\Models\LeaveType;
// use Illuminate\Database\Seeder;
// use Illuminate\Support\Arr;
// use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Schema;

// class EmployeeFullSeeder extends Seeder
// {
//     public function run(): void
//     {
//         DB::table('companies')->updateOrInsert(
//             ['id' => 1],
//             [
//                 'name' => 'Test Company',
//                 'slug' => 'test-company',
//                 'status' => 'active',
//                 'created_at' => now(),
//                 'updated_at' => now(),
//             ]
//         );

//         $departmentNames = ['IT', 'HR', 'Marketing', 'Finance', 'Support'];
//         $leaveTypeNames = ['Annual Leave', 'Sick Leave', 'Maternity', 'Casual Leave'];

//         $departments = collect($departmentNames)->map(function (string $name) {
//             return Department::query()->updateOrCreate(
//                 ['company_id' => 1, 'name' => $name],
//                 [
//                     'description' => $name . ' Department',
//                     'status' => 'active',
//                 ]
//             );
//         });

//         for ($i = 1; $i <= 5; $i++) {
//             $fullName = "Test Employee {$i}";
//             $email = "employee{$i}@example.com";
//             $username = "employee{$i}";

//             $baseSalary = random_int(1000, 3000);
//             $allowance = random_int(100, 500);
//             $department = $departments->random();

//             $employee = Employee::query()->updateOrCreate(
//                 ['email' => $email],
//                 [
//                     'company_id' => 1,
//                     'employee_code' => sprintf('TEST-EMP-%04d', $i),
//                     'first_name' => $fullName,
//                     'last_name' => null,
//                     'department' => $department->name,
//                     'department_id' => $department->id,
//                     'base_salary' => $baseSalary,
//                     'salary' => $baseSalary,
//                     'start_date' => now()->toDateString(),
//                     'status' => 'active',
//                 ]
//             );

//             $userPayload = [
//                 'name' => $fullName,
//                 'email' => $email,
//                 'password' => bcrypt('password123'),
//                 'company_id' => 1,
//                 'employee_id' => $employee->id,
//                 'updated_at' => now(),
//             ];

//             if (Schema::hasColumn('users', 'username')) {
//                 $userPayload['username'] = $username;
//             }

//             if (Schema::hasColumn('users', 'status')) {
//                 $userPayload['status'] = 'active';
//             }

//             if (Schema::hasColumn('users', 'role_id')) {
//                 $userPayload['role_id'] = 2;
//             }

//             if (Schema::hasColumn('users', 'role')) {
//                 $userPayload['role'] = 'employee';
//             }

//             DB::table('users')->updateOrInsert(
//                 ['email' => $email],
//                 array_merge(['created_at' => now()], $userPayload)
//             );

//             EmployeeBenefit::query()->updateOrCreate(
//                 [
//                     'company_id' => 1,
//                     'employee_id' => $employee->id,
//                     'benefit_name' => 'Allowance',
//                 ],
//                 [
//                     'amount' => $allowance,
//                     'type' => 'fixed',
//                 ]
//             );

//             $leaveType = LeaveType::query()->updateOrCreate(
//                 [
//                     'company_id' => 1,
//                     'name' => Arr::random($leaveTypeNames),
//                 ],
//                 [
//                     'default_days' => 12,
//                     'description' => 'Auto-created by EmployeeFullSeeder',
//                 ]
//             );

//             LeaveAllocation::query()->updateOrCreate(
//                 [
//                     'company_id' => 1,
//                     'employee_id' => $employee->id,
//                     'leave_type_id' => $leaveType->id,
//                     'year' => (int) now()->year,
//                 ],
//                 [
//                     'days_allocated' => 12,
//                     'days_used' => 0,
//                 ]
//             );
//         }
//     }
// }
