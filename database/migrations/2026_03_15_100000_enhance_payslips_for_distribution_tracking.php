<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            if (! Schema::hasColumn('payslips', 'delivery_status')) {
                $table->string('delivery_status')->default('pending')->after('generated_at');
            }
            if (! Schema::hasColumn('payslips', 'delivery_channel')) {
                $table->string('delivery_channel')->nullable()->after('delivery_status');
            }
            if (! Schema::hasColumn('payslips', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('delivery_channel');
            }
            if (! Schema::hasColumn('payslips', 'emailed_to')) {
                $table->string('emailed_to')->nullable()->after('delivered_at');
            }
            if (! Schema::hasColumn('payslips', 'last_error')) {
                $table->text('last_error')->nullable()->after('emailed_to');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payslips', function (Blueprint $table) {
            foreach (['delivery_status', 'delivery_channel', 'delivered_at', 'emailed_to', 'last_error'] as $column) {
                if (Schema::hasColumn('payslips', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
