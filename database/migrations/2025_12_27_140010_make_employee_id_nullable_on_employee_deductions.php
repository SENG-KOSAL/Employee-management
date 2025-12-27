<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Allow deductions to be created without assigning an employee immediately
        DB::statement('ALTER TABLE employee_deductions ALTER COLUMN employee_id DROP NOT NULL');
    }

    public function down(): void
    {
        // Revert to NOT NULL (fails if null values remain)
        DB::statement('ALTER TABLE employee_deductions ALTER COLUMN employee_id SET NOT NULL');
    }
};
