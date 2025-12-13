<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_code', 50)->unique();
            $table->string('first_name', 150);
            $table->string('last_name', 150)->nullable();
            $table->string('email')->unique();
            $table->string('phone', 50)->nullable();
            $table->enum('gender', ['male','female','other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->text('address')->nullable();
            $table->string('department', 150)->nullable();
            $table->string('position', 150)->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('position_id')->nullable();
            $table->date('start_date')->nullable();
            $table->decimal('salary', 15, 2)->nullable();
            $table->enum('status', ['active','inactive'])->default('active');
            $table->unsignedBigInteger('line_manager_id')->nullable()->index();
            $table->string('photo_path')->nullable();
            $table->timestamps();

            // self-referential FK
            $table->foreign('line_manager_id')->references('id')->on('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (Schema::hasColumn('employees', 'line_manager_id')) {
                $table->dropForeign(['line_manager_id']);
            }
        });
        Schema::dropIfExists('employees');
    }
};