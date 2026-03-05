<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_name_unique');
            $table->unique(['company_id', 'name'], 'departments_company_name_unique');
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropUnique('leave_types_name_unique');
            $table->unique(['company_id', 'name'], 'leave_types_company_name_unique');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_employee_code_unique');
            $table->unique(['company_id', 'employee_code'], 'employees_company_employee_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique('departments_company_name_unique');
            $table->unique('name');
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropUnique('leave_types_company_name_unique');
            $table->unique('name');
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->dropUnique('employees_company_employee_code_unique');
            $table->unique('employee_code');
        });
    }
};
