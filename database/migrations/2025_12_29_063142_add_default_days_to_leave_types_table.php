<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('leave_types', 'default_days')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->integer('default_days')->default(0)->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_types', 'default_days')) {
            Schema::table('leave_types', function (Blueprint $table) {
                $table->dropColumn('default_days');
            });
        }
    }
};
