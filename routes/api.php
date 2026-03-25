<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmployeeAuditController;
use App\Http\Controllers\Api\EmployeeBenefitController;
use App\Http\Controllers\Api\EmployeeBulkActionController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeDeductionController;
use App\Http\Controllers\Api\EmployeeExcelController;
use App\Http\Controllers\Api\EmployeeWorkScheduleController;
use App\Http\Controllers\Api\LeaveAllocationController;
use App\Http\Controllers\Api\LeaveApprovalController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\OvertimeController;
use App\Http\Controllers\Api\PayrollGovernanceController;
use App\Http\Controllers\Api\PayrollRunController;
use App\Http\Controllers\Api\PayslipController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\ReportingController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\SalaryController;
use App\Http\Controllers\Api\ScheduledReportController;
use App\Http\Controllers\Api\WorkScheduleController;
use App\Http\Controllers\Api\TenantContextController;
use App\Http\Controllers\Api\UserNotificationController;
use App\Http\Controllers\Api\Platform\CompanyController as PlatformCompanyController;
use App\Http\Controllers\Api\Platform\ContextController;
use App\Http\Controllers\Api\Platform\ModuleFlagController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'active-company'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('tenant-context', [TenantContextController::class, 'show']);

        // Platform (super admin) endpoints
        Route::prefix('platform')->middleware(['role:super_admin', 'audit'])->group(function () {
            Route::get('companies', [PlatformCompanyController::class, 'index']);
            Route::post('companies', [PlatformCompanyController::class, 'store']);
            Route::get('companies/{company}', [PlatformCompanyController::class, 'show']);
            Route::put('companies/{company}', [PlatformCompanyController::class, 'update']);
            Route::delete('companies/{company}', [PlatformCompanyController::class, 'destroy']);
            Route::post('companies/{company}/enable', [PlatformCompanyController::class, 'enable']);
            Route::post('companies/{company}/suspend', [PlatformCompanyController::class, 'suspend']);
            Route::post('companies/{company}/admins', [PlatformCompanyController::class, 'createAdmin']);

            Route::get('companies/{company}/modules', [ModuleFlagController::class, 'index']);
            Route::put('companies/{company}/modules', [ModuleFlagController::class, 'update']);

            Route::post('context/enter', [ContextController::class, 'enter']);
            Route::post('context/exit', [ContextController::class, 'exit']);
        });

        // Tenant-scoped routes
        Route::middleware(['require-company', 'superadmin-readonly', 'audit'])->group(function () {
            // attendance actions
            Route::post('attendances/clock-in', [AttendanceController::class, 'clockIn']);
            Route::post('attendances/clock-out', [AttendanceController::class, 'clockOut']);

            // CRUD / listing / summary (protected)
            Route::get('attendances', [AttendanceController::class, 'index']);
            Route::get('attendances/summary', [AttendanceController::class, 'summary']);
            Route::match(['patch', 'put'], 'attendances/{attendance}/adjust', [AttendanceController::class, 'adjust']);

            // leave types (admin/hr manage)
            Route::apiResource('leave-types', LeaveTypeController::class);
            
            // leave requests
            Route::get('leave-requests', [LeaveRequestController::class, 'index']);
            Route::post('leave-requests', [LeaveRequestController::class, 'store']);
            Route::get('leave-requests/{leave_request}', [LeaveRequestController::class, 'show']);
            Route::post('leave-requests/{leave_request}/cancel', [LeaveRequestController::class, 'cancel']);

            // approvals
            Route::match(['post', 'patch', 'put'], 'leave-requests/{leave_request}/decide', [LeaveApprovalController::class, 'decide']);
            Route::match(['post', 'patch', 'put'], 'leave-requests/{leave_request}/approve', [LeaveApprovalController::class, 'approve']);
            Route::match(['post', 'patch', 'put'], 'leave-requests/{leave_request}/reject', [LeaveApprovalController::class, 'reject']);

            // Employee self-service payrolls
            Route::middleware(['role:employee'])->group(function () {
                Route::get('me/payrolls', [\App\Http\Controllers\Api\PayrollController::class, 'myPayrolls']);
            });

            // Protected routes (admin/hr/manager; further checks in controllers)
            Route::post('users', [AuthController::class, 'createUser'])->middleware('can:create-user');
            Route::apiResource('employees', EmployeeController::class);
            Route::post('employees/bulk-actions', [EmployeeBulkActionController::class, 'handle'])->middleware('role:admin,hr,company_admin');
            Route::get('employees/{employee}/audit-trail', [EmployeeAuditController::class, 'index']);

            Route::post('employees/{employee}/photo', [EmployeeController::class, 'uploadPhoto']);
            Route::match(['post', 'put', 'patch'], 'employees/{employee}/documents', [EmployeeController::class, 'uploadDocuments']);

            Route::prefix('admin/employees')->middleware('role:admin,hr,company_admin')->group(function () {
                Route::get('export', [EmployeeExcelController::class, 'export']);
                Route::get('template', [EmployeeExcelController::class, 'template']);
                Route::post('import', [EmployeeExcelController::class, 'import']);
            });

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
            Route::get('payrolls', [\App\Http\Controllers\Api\PayrollController::class, 'index'])->middleware('role:admin,hr,company_admin');
            Route::post('payrolls', [\App\Http\Controllers\Api\PayrollController::class, 'store'])->middleware('role:admin,hr,company_admin');
            Route::get('payrolls/{payroll}', [\App\Http\Controllers\Api\PayrollController::class, 'show'])->middleware('role:admin,hr,company_admin');
            Route::patch('payrolls/{payroll}', [\App\Http\Controllers\Api\PayrollController::class, 'update'])->middleware('role:admin,hr,company_admin');
            Route::get('payrolls/{payroll}/adjustments', [\App\Http\Controllers\Api\PayrollController::class, 'listAdjustments'])->middleware('role:admin,hr,company_admin');
            Route::post('payrolls/{payroll}/adjustments', [\App\Http\Controllers\Api\PayrollController::class, 'createAdjustment'])->middleware('role:admin,hr,company_admin');
            Route::post('payrolls/{payroll}/adjustments/{adjustment}/approve', [PayrollGovernanceController::class, 'approveAdjustment'])->middleware('role:admin,hr,company_admin');
            Route::post('payrolls/{payroll}/adjustments/{adjustment}/reject', [PayrollGovernanceController::class, 'rejectAdjustment'])->middleware('role:admin,hr,company_admin');
            Route::post('payrolls/{payroll}/mark-paid', [\App\Http\Controllers\Api\PayrollController::class, 'markPaid'])->middleware('role:admin,hr,company_admin');

            // payslip generation and distribution
            Route::post('payrolls/{payroll}/payslip/generate', [PayslipController::class, 'generate'])->middleware('role:admin,hr,company_admin');
            Route::post('payrolls/payslips/generate-batch', [PayslipController::class, 'generateBatch'])->middleware('role:admin,hr,company_admin');
            Route::post('payrolls/{payroll}/payslip/distribute', [PayslipController::class, 'distribute'])->middleware('role:admin,hr,company_admin');
            Route::get('payrolls/{payroll}/payslip/download', [PayslipController::class, 'download']);

            // payroll governance
            Route::get('payroll-period-locks', [PayrollGovernanceController::class, 'listLocks'])->middleware('role:admin,hr,company_admin');
            Route::post('payroll-periods/lock', [PayrollGovernanceController::class, 'lockPeriod'])->middleware('role:admin,hr,company_admin');
            Route::post('payroll-periods/unlock', [PayrollGovernanceController::class, 'unlockPeriod'])->middleware('role:admin,hr,company_admin');

            // reporting and analytics
            Route::get('reports/summary', [ReportingController::class, 'summary'])->middleware('role:admin,hr,manager,company_admin');
            Route::get('reports/drilldown/employees', [ReportingController::class, 'drilldownEmployees'])->middleware('role:admin,hr,manager,company_admin');
            Route::get('reports/drilldown/leaves', [ReportingController::class, 'drilldownLeaves'])->middleware('role:admin,hr,manager,company_admin');
            Route::get('reports/drilldown/payrolls', [ReportingController::class, 'drilldownPayrolls'])->middleware('role:admin,hr,manager,company_admin');
            Route::get('reports/export', [ReportingController::class, 'export'])->middleware('role:admin,hr,manager,company_admin');

            // scheduled reports
            Route::get('scheduled-reports', [ScheduledReportController::class, 'index'])->middleware('role:admin,hr,company_admin');
            Route::post('scheduled-reports', [ScheduledReportController::class, 'store'])->middleware('role:admin,hr,company_admin');
            Route::get('scheduled-reports/{scheduled_report}', [ScheduledReportController::class, 'show'])->middleware('role:admin,hr,company_admin');
            Route::patch('scheduled-reports/{scheduled_report}', [ScheduledReportController::class, 'update'])->middleware('role:admin,hr,company_admin');
            Route::delete('scheduled-reports/{scheduled_report}', [ScheduledReportController::class, 'destroy'])->middleware('role:admin,hr,company_admin');
            Route::post('scheduled-reports/{scheduled_report}/run-now', [ScheduledReportController::class, 'runNow'])->middleware('role:admin,hr,company_admin');

            // audit and notifications
            Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('role:admin,hr,manager,company_admin');
            Route::get('notifications/unread', [UserNotificationController::class, 'unread']);
            Route::post('notifications/{notificationId}/read', [UserNotificationController::class, 'markAsRead']);
            Route::post('notifications/read-all', [UserNotificationController::class, 'markAllAsRead']);

            // payroll runs
            Route::get('payroll-runs', [PayrollRunController::class, 'index'])->middleware('role:admin,hr,company_admin');
            Route::post('payroll-runs', [PayrollRunController::class, 'store'])->middleware('role:admin,hr,company_admin');
            Route::get('payroll-runs/{payroll_run}', [PayrollRunController::class, 'show'])->middleware('role:admin,hr,company_admin');
            Route::post('payroll-runs/{payroll_run}/approve', [PayrollRunController::class, 'approve'])->middleware('role:admin,hr,company_admin');
            Route::post('payroll-runs/{payroll_run}/mark-paid', [PayrollRunController::class, 'markPaid'])->middleware('role:admin,hr,company_admin');

            // permissions management (admin only)
            Route::get('permissions', [PermissionController::class, 'index'])->middleware('role:admin,company_admin');
            Route::post('permissions', [PermissionController::class, 'store'])->middleware('role:admin,company_admin');
            Route::get('roles/{role}/permissions', [RolePermissionController::class, 'show'])->middleware('role:admin,company_admin');
            Route::put('roles/{role}/permissions', [RolePermissionController::class, 'update'])->middleware('role:admin,company_admin');
        });
    });
});