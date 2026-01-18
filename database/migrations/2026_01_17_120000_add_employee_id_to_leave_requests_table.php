<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leave_requests', 'employee_id')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->foreignId('employee_id')->after('id')->constrained('employees')->cascadeOnDelete();
                $table->index(['employee_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_requests', 'employee_id')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->dropIndex(['employee_id']);
                $table->dropConstrainedForeignId('employee_id');
            });
        }
    }
};
