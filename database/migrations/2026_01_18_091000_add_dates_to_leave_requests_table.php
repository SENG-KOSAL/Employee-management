<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_requests', 'start_date')) {
                $table->date('start_date')->after('leave_type_id');
            }
            if (! Schema::hasColumn('leave_requests', 'end_date')) {
                $table->date('end_date')->after('start_date');
            }
            if (! Schema::hasColumn('leave_requests', 'days')) {
                $table->decimal('days', 8, 2)->after('end_date');
            }
            if (! Schema::hasColumn('leave_requests', 'reason')) {
                $table->text('reason')->nullable()->after('days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            if (Schema::hasColumn('leave_requests', 'reason')) {
                $table->dropColumn('reason');
            }
            if (Schema::hasColumn('leave_requests', 'days')) {
                $table->dropColumn('days');
            }
            if (Schema::hasColumn('leave_requests', 'end_date')) {
                $table->dropColumn('end_date');
            }
            if (Schema::hasColumn('leave_requests', 'start_date')) {
                $table->dropColumn('start_date');
            }
        });
    }
};
