<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'company_id')) {
                    $table->foreignId('company_id')->nullable()->after('role')->constrained('companies')->nullOnDelete();
                }

                if (! Schema::hasColumn('users', 'status')) {
                    $table->enum('status', ['active', 'suspended'])->default('active')->after('password');
                }

                if (! Schema::hasColumn('users', 'last_login_at')) {
                    $table->timestamp('last_login_at')->nullable()->after('status');
                }
            });

            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                // Expand MySQL enum values.
                DB::statement("ALTER TABLE users MODIFY role ENUM('super_admin','company_admin','admin','hr','manager','employee') NOT NULL DEFAULT 'employee'");
            } elseif ($driver === 'pgsql') {
                // PostgreSQL: drop existing check constraint then set type/default.
                DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
                DB::statement("ALTER TABLE users ALTER COLUMN role TYPE VARCHAR(32)");
                DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'employee'");
                DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('super_admin','company_admin','admin','hr','manager','employee'))");
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'company_id')) {
                    $table->dropConstrainedForeignId('company_id');
                }
                if (Schema::hasColumn('users', 'status')) {
                    $table->dropColumn('status');
                }
                if (Schema::hasColumn('users', 'last_login_at')) {
                    $table->dropColumn('last_login_at');
                }
            });

            $driver = Schema::getConnection()->getDriverName();

            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE users MODIFY role ENUM('admin','hr','manager','employee') NOT NULL DEFAULT 'employee'");
            } elseif ($driver === 'pgsql') {
                DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
                DB::statement("ALTER TABLE users ALTER COLUMN role TYPE VARCHAR(32)");
                DB::statement("ALTER TABLE users ALTER COLUMN role SET DEFAULT 'employee'");
                DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('admin','hr','manager','employee'))");
            }
        }
    }
};
