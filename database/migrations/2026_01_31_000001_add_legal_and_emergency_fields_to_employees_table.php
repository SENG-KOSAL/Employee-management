<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (! Schema::hasColumn('employees', 'national_id_number')) {
                $table->string('national_id_number', 50)->nullable()->after('photo_path');
            }
            if (! Schema::hasColumn('employees', 'nssf_number')) {
                $table->string('nssf_number', 50)->nullable()->after('national_id_number');
            }
            if (! Schema::hasColumn('employees', 'passport_number')) {
                $table->string('passport_number', 50)->nullable()->after('nssf_number');
            }
            if (! Schema::hasColumn('employees', 'work_permit_number')) {
                $table->string('work_permit_number', 50)->nullable()->after('passport_number');
            }
            if (! Schema::hasColumn('employees', 'nationality')) {
                // recommended values: khmer | foreign
                $table->string('nationality', 20)->nullable()->after('work_permit_number');
            }

            if (! Schema::hasColumn('employees', 'emergency_contact_name')) {
                $table->string('emergency_contact_name', 150)->nullable()->after('nationality');
            }
            if (! Schema::hasColumn('employees', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone', 30)->nullable()->after('emergency_contact_name');
            }
            if (! Schema::hasColumn('employees', 'emergency_contact_relationship')) {
                $table->string('emergency_contact_relationship', 50)->nullable()->after('emergency_contact_phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $columns = [
                'national_id_number',
                'nssf_number',
                'passport_number',
                'work_permit_number',
                'nationality',
                'emergency_contact_name',
                'emergency_contact_phone',
                'emergency_contact_relationship',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('employees', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
