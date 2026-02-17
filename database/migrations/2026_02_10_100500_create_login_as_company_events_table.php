<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('login_as_company_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('super_admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->timestamp('entered_at')->useCurrent();
            $table->timestamp('exited_at')->nullable();
            $table->string('reason')->nullable();
            $table->json('audit_trail')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_as_company_events');
    }
};
