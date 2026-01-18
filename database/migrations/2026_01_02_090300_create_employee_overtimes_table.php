<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_overtimes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('hours', 5, 2);
            $table->decimal('rate', 8, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date'], 'employee_overtimes_employee_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_overtimes');
    }
};
