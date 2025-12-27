<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('benefit_name', 191);
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['fixed', 'percentage'])->default('fixed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_benefits');
    }
};
