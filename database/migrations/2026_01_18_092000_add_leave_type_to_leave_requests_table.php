<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_requests', 'leave_type_id')) {
                $table->foreignId('leave_type_id')->after('employee_id')->constrained('leave_types')->cascadeOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            if (Schema::hasColumn('leave_requests', 'leave_type_id')) {
                $table->dropConstrainedForeignId('leave_type_id');
            }
        });
    }
};
