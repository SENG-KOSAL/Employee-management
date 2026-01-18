<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_allocations', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_allocations', 'employee_id')) {
                $table->foreignId('employee_id')->after('id')->constrained('employees')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('leave_allocations', 'leave_type_id')) {
                $table->foreignId('leave_type_id')->after('employee_id')->constrained('leave_types')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('leave_allocations', 'year')) {
                $table->integer('year')->after('leave_type_id')->index();
            }
            if (! Schema::hasColumn('leave_allocations', 'days_allocated')) {
                $table->decimal('days_allocated', 8, 2)->default(0)->after('year');
            }
            if (! Schema::hasColumn('leave_allocations', 'days_used')) {
                $table->decimal('days_used', 8, 2)->default(0)->after('days_allocated');
            }
        });

        // Ensure unique constraint exists (PostgreSQL-safe)
        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'alloc_employee_type_year_unique'
    ) THEN
        ALTER TABLE leave_allocations
            ADD CONSTRAINT alloc_employee_type_year_unique
            UNIQUE (employee_id, leave_type_id, year);
    END IF;
END$$;
SQL);
    }

    public function down(): void
    {
        Schema::table('leave_allocations', function (Blueprint $table) {
            // Only drop the unique constraint; keep columns to avoid data loss
            DB::statement("DO $$ BEGIN IF EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'alloc_employee_type_year_unique') THEN ALTER TABLE leave_allocations DROP CONSTRAINT alloc_employee_type_year_unique; END IF; END$$;");
        });
    }
};
