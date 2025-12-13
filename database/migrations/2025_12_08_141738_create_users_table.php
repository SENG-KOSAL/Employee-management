<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
         Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['admin','hr','manager','employee'])->default('employee')->index();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->timestamps();

            // Add FK if employees table exists
            $table->foreign('employee_id')->references('id')->on('employees')->nullOnDelete();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
     {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'employee_id')) {
                $table->dropForeign(['employee_id']);
            }
        });
        Schema::dropIfExists('users');
    }
};
