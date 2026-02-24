<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_requests', 'approver_id')) {
                $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('leave_requests', 'approver_notes')) {
                $table->text('approver_notes')->nullable();
            }

            if (! Schema::hasColumn('leave_requests', 'decided_at')) {
                // Keep timezone to match other leave columns.
                $table->timestampTz('decided_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            if (Schema::hasColumn('leave_requests', 'approver_id')) {
                try {
                    $table->dropConstrainedForeignId('approver_id');
                } catch (\Throwable $e) {
                    $table->dropColumn('approver_id');
                }
            }

            if (Schema::hasColumn('leave_requests', 'approver_notes')) {
                $table->dropColumn('approver_notes');
            }

            if (Schema::hasColumn('leave_requests', 'decided_at')) {
                $table->dropColumn('decided_at');
            }
        });
    }
};
