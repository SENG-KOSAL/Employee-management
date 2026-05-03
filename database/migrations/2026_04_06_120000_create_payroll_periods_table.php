<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('payment_date');
            $table->boolean('is_locked')->default(false);
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'start_date']);
            $table->index(['company_id', 'end_date']);
            $table->index(['company_id', 'deleted_at']);
        });

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'pgsql'], true)) {
            DB::statement('ALTER TABLE payroll_periods ADD CONSTRAINT payroll_periods_end_after_start CHECK (end_date > start_date)');
            DB::statement('ALTER TABLE payroll_periods ADD CONSTRAINT payroll_periods_payment_on_or_after_end CHECK (payment_date >= end_date)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_periods');
    }
};
