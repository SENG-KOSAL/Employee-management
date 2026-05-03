<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_period_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->foreignId('locked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'period_start', 'period_end'], 'payroll_period_lock_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_period_locks');
    }
};
