<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date')->index(); // convenience to query by day
            $table->timestampTz('check_in')->nullable();
            $table->timestampTz('check_out')->nullable();
            $table->decimal('total_hours', 8, 2)->nullable(); // hours with decimals
            $table->boolean('is_late')->default(false);
            $table->decimal('overtime_hours', 8, 2)->default(0);
            $table->enum('attendance_status', ['present', 'absent'])->default('present')->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date'], 'attendance_employee_date_unique');
            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};