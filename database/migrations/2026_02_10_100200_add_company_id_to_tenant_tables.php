<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected array $tables = [
        'employees',
        'departments',
        'attendances',
        'leave_types',
        'leave_allocations',
        'leave_requests',
        'employee_benefits',
        'employee_deductions',
        'work_schedules',
        'employee_work_schedules',
        'overtimes',
        'payrolls',
        'payroll_runs',
        'payroll_adjustments',
        'payroll_audit_logs',
        'payslips',
        'employee_documents',
    ];

    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            if (Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'company_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropConstrainedForeignId('company_id');
            });
        }
    }
};
