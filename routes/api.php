<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\LeaveApprovalController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\LeaveAllocationController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeBenefitController;
use App\Http\Controllers\Api\EmployeeDeductionController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\EmployeeWorkScheduleController;
use App\Http\Controllers\Api\WorkScheduleController;
use App\Http\Controllers\Api\OvertimeController;
use App\Http\Controllers\Api\PayrollRunController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\RolePermissionController;
use Illuminate\Support\Facades\Route;

// Public auth routes
Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('me', [AuthController::class, 'me'])->middleware('auth:sanctum');
    // attendance actions
    Route::post('attendances/clock-in', [AttendanceController::class, 'clockIn'])->middleware('auth:sanctum');
    Route::post('attendances/clock-out', [AttendanceController::class, 'clockOut'])->middleware('auth:sanctum');

    // CRUD / listing / summary (protected)
    Route::get('attendances', [AttendanceController::class, 'index'])->middleware('auth:sanctum');
    Route::get('attendances/summary', [AttendanceController::class, 'summary'])->middleware('auth:sanctum');
    Route::match(['patch', 'put'], 'attendances/{attendance}/adjust', [AttendanceController::class, 'adjust'])->middleware('auth:sanctum');
    // Optionally register resource routes for Admin/HR to create/edit/delete attendances:
    // Route::apiResource('attendances', AttendanceController::class)->except(['store','update','destroy'])->middleware('auth:sanctum');


    // leave types (admin/hr manage)
    Route::apiResource('leave-types', LeaveTypeController::class)->middleware('auth:sanctum');
    
    // leave requests
    Route::get('leave-requests', [LeaveRequestController::class, 'index'])->middleware('auth:sanctum');
    Route::post('leave-requests', [LeaveRequestController::class, 'store'])->middleware('auth:sanctum');
    Route::get('leave-requests/{leave_request}', [LeaveRequestController::class, 'show'])->middleware('auth:sanctum');
    Route::post('leave-requests/{leave_request}/cancel', [LeaveRequestController::class, 'cancel'])->middleware('auth:sanctum');

    // appr
    Route::match(['post', 'patch', 'put'], 'leave-requests/{leave_request}/decide', [LeaveApprovalController::class, 'decide'])->middleware('auth:sanctum');
    Route::match(['post', 'patch', 'put'], 'leave-requests/{leave_request}/approve', [LeaveApprovalController::class, 'approve'])->middleware('auth:sanctum');
    Route::match(['post', 'patch', 'put'], 'leave-requests/{leave_request}/reject', [LeaveApprovalController::class, 'reject'])->middleware('auth:sanctum');
});

// Employee self-service payrolls
Route::prefix('v1')->middleware(['auth:sanctum', 'role:employee'])->group(function () {
    Route::get('me/payrolls', [\App\Http\Controllers\Api\PayrollController::class, 'myPayrolls']);
});

// Protected routes (admin/hr/manager; further checks in controllers)
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('users', [AuthController::class, 'createUser'])->middleware('can:create-user');
    Route::apiResource('employees', EmployeeController::class);

    Route::post('employees/{employee}/photo', [EmployeeController::class, 'uploadPhoto']);
    Route::match(['post', 'put', 'patch'], 'employees/{employee}/documents', [EmployeeController::class, 'uploadDocuments']);

    


    // Department management (controller enforces role-based access)
    Route::get('departments', [DepartmentController::class, 'index']);
    Route::post('departments', [DepartmentController::class, 'store']);
    Route::get('departments/{department}', [DepartmentController::class, 'show']);
    Route::put('departments/{department}', [DepartmentController::class, 'update']);
    Route::patch('departments/{department}', [DepartmentController::class, 'update']);
    Route::delete('departments/{department}', [DepartmentController::class, 'destroy']);

    // Salary management
    Route::apiResource('employee-benefits', EmployeeBenefitController::class);
    Route::apiResource('employee-deductions', EmployeeDeductionController::class);
    Route::apiResource('leave-allocations', LeaveAllocationController::class);
    Route::apiResource('work-schedules', WorkScheduleController::class);
    Route::apiResource('employee-work-schedules', EmployeeWorkScheduleController::class);
    Route::apiResource('overtimes', OvertimeController::class);
    Route::post('overtimes/{overtime}/approve', [OvertimeController::class, 'approve']);
    Route::post('overtimes/{overtime}/reject', [OvertimeController::class, 'reject']);
    // salary viewing
    Route::get('salaries', [SalaryController::class, 'index']);
    Route::get('salary/{employee}', [SalaryController::class, 'show']);

    // payroll management (admin/hr only enforced in controller; add role middleware if desired)
    Route::get('payrolls', [\App\Http\Controllers\Api\PayrollController::class, 'index'])->middleware('role:admin,hr');
    
    Route::post('payrolls', [\App\Http\Controllers\Api\PayrollController::class, 'store'])->middleware('role:admin,hr');
    Route::get('payrolls/{payroll}', [\App\Http\Controllers\Api\PayrollController::class, 'show'])->middleware('role:admin,hr');
    Route::patch('payrolls/{payroll}', [\App\Http\Controllers\Api\PayrollController::class, 'update'])->middleware('role:admin,hr');
    Route::get('payrolls/{payroll}/adjustments', [\App\Http\Controllers\Api\PayrollController::class, 'listAdjustments'])->middleware('role:admin,hr');
    Route::post('payrolls/{payroll}/adjustments', [\App\Http\Controllers\Api\PayrollController::class, 'createAdjustment'])->middleware('role:admin,hr');
    Route::post('payrolls/{payroll}/mark-paid', [\App\Http\Controllers\Api\PayrollController::class, 'markPaid'])->middleware('role:admin,hr');

    // payroll runs
    Route::get('payroll-runs', [PayrollRunController::class, 'index'])->middleware('role:admin,hr');
    Route::post('payroll-runs', [PayrollRunController::class, 'store'])->middleware('role:admin,hr');
    Route::get('payroll-runs/{payroll_run}', [PayrollRunController::class, 'show'])->middleware('role:admin,hr');
    Route::post('payroll-runs/{payroll_run}/approve', [PayrollRunController::class, 'approve'])->middleware('role:admin,hr');
    Route::post('payroll-runs/{payroll_run}/mark-paid', [PayrollRunController::class, 'markPaid'])->middleware('role:admin,hr');

    // permissions management (admin only)
    Route::get('permissions', [PermissionController::class, 'index'])->middleware('role:admin');
    Route::post('permissions', [PermissionController::class, 'store'])->middleware('role:admin');
    Route::get('roles/{role}/permissions', [RolePermissionController::class, 'show'])->middleware('role:admin');
    Route::put('roles/{role}/permissions', [RolePermissionController::class, 'update'])->middleware('role:admin');
});