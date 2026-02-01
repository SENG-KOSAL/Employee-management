<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_documents')) {
            return;
        }

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();

            // Upload / reference paths (stored on disk; e.g. public/private)
            $table->string('id_card_file_path')->nullable();
            $table->string('contract_file_path')->nullable();
            $table->string('cv_file_path')->nullable();
            $table->string('certificate_file_path')->nullable();

            $table->timestamps();

            // one row per employee (simple)
            $table->unique('employee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
