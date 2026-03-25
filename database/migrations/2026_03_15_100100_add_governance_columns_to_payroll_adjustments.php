<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_adjustments', 'status')) {
                $table->string('status')->default('pending')->after('description');
            }
            if (! Schema::hasColumn('payroll_adjustments', 'requested_by')) {
                $table->foreignId('requested_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('payroll_adjustments', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('payroll_adjustments', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('payroll_adjustments', 'rejected_by')) {
                $table->foreignId('rejected_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('payroll_adjustments', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
            if (! Schema::hasColumn('payroll_adjustments', 'decision_notes')) {
                $table->text('decision_notes')->nullable()->after('rejected_at');
            }
            if (! Schema::hasColumn('payroll_adjustments', 'applied_at')) {
                $table->timestamp('applied_at')->nullable()->after('decision_notes');
            }
            if (! Schema::hasColumn('payroll_adjustments', 'exception_flags')) {
                $table->json('exception_flags')->nullable()->after('applied_at');
            }

            $table->index(['payroll_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_adjustments', 'requested_by')) {
                $table->dropConstrainedForeignId('requested_by');
            }
            if (Schema::hasColumn('payroll_adjustments', 'approved_by')) {
                $table->dropConstrainedForeignId('approved_by');
            }
            if (Schema::hasColumn('payroll_adjustments', 'rejected_by')) {
                $table->dropConstrainedForeignId('rejected_by');
            }

            foreach (['status', 'approved_at', 'rejected_at', 'decision_notes', 'applied_at', 'exception_flags'] as $column) {
                if (Schema::hasColumn('payroll_adjustments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
