<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            if (! Schema::hasColumn('payroll_runs', 'month')) {
                $table->unsignedTinyInteger('month')->nullable()->after('company_id');
            }

            if (! Schema::hasColumn('payroll_runs', 'year')) {
                $table->unsignedSmallInteger('year')->nullable()->after('month');
            }

            if (! Schema::hasColumn('payroll_runs', 'payment_date')) {
                $table->date('payment_date')->nullable()->after('period_end');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            if (Schema::hasColumn('payroll_runs', 'payment_date')) {
                $table->dropColumn('payment_date');
            }

            if (Schema::hasColumn('payroll_runs', 'year')) {
                $table->dropColumn('year');
            }

            if (Schema::hasColumn('payroll_runs', 'month')) {
                $table->dropColumn('month');
            }
        });
    }
};
