<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class EmployeeImportSchema
{
    private const TABLE = 'employees';

    public function importableColumns(): array
    {
        $excluded = config('employee_import.excluded_columns', [
            'id',
            'company_id',
            'created_at',
            'updated_at',
            'deleted_at',
        ]);

        $schemaColumns = Schema::getColumnListing(self::TABLE);
        $fillable = (new Employee())->getFillable();

        return array_values(array_filter($schemaColumns, function (string $column) use ($fillable, $excluded) {
            return in_array($column, $fillable, true) && ! in_array($column, $excluded, true);
        }));
    }

    public function requiredColumns(): array
    {
        $meta = $this->columnMeta();
        $importable = $this->importableColumns();

        $required = [];
        foreach ($importable as $column) {
            $columnMeta = $meta[$column] ?? null;
            if (! $columnMeta) {
                continue;
            }

            if ($columnMeta['nullable'] === false && $columnMeta['has_default'] === false) {
                $required[] = $column;
            }
        }

        $forcedRequired = config('employee_import.required_columns', []);

        return array_values(array_unique(array_merge($required, $forcedRequired)));
    }

    public function foreignKeys(): array
    {
        $databaseForeignKeys = $this->databaseForeignKeys();
        $configuredForeignKeys = config('employee_import.foreign_keys', []);

        return array_merge($databaseForeignKeys, $configuredForeignKeys);
    }

    public function inferForeignKeysFromColumns(array $columns): array
    {
        $inferred = [];

        foreach ($columns as $column) {
            if (! Str::endsWith($column, '_id')) {
                continue;
            }

            if (in_array($column, ['id', 'company_id'], true)) {
                continue;
            }

            $table = Str::plural(Str::beforeLast($column, '_id'));

            if (! Schema::hasTable($table)) {
                continue;
            }

            $inferred[$column] = [
                'table' => $table,
                'column' => 'id',
            ];
        }

        return $inferred;
    }

    private function columnMeta(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $rows = $connection->select(
                'SELECT column_name, is_nullable, column_default
                 FROM information_schema.columns
                 WHERE table_schema = current_schema() AND table_name = ?',
                [self::TABLE]
            );

            $meta = [];
            foreach ($rows as $row) {
                $meta[$row->column_name] = [
                    'nullable' => $row->is_nullable === 'YES',
                    'has_default' => $row->column_default !== null,
                ];
            }

            return $meta;
        }

        if ($driver === 'mysql') {
            $rows = $connection->select(
                'SELECT column_name, is_nullable, column_default
                 FROM information_schema.columns
                 WHERE table_schema = database() AND table_name = ?',
                [self::TABLE]
            );

            $meta = [];
            foreach ($rows as $row) {
                $meta[$row->column_name] = [
                    'nullable' => $row->is_nullable === 'YES',
                    'has_default' => $row->column_default !== null,
                ];
            }

            return $meta;
        }

        if ($driver === 'sqlite') {
            $rows = $connection->select("PRAGMA table_info('" . self::TABLE . "')");

            $meta = [];
            foreach ($rows as $row) {
                $meta[$row->name] = [
                    'nullable' => (int) $row->notnull === 0,
                    'has_default' => $row->dflt_value !== null,
                ];
            }

            return $meta;
        }

        return [];
    }

    private function databaseForeignKeys(): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'pgsql') {
            $rows = $connection->select(
                'SELECT kcu.column_name,
                        ccu.table_name AS foreign_table_name,
                        ccu.column_name AS foreign_column_name
                 FROM information_schema.table_constraints tc
                 JOIN information_schema.key_column_usage kcu
                   ON tc.constraint_name = kcu.constraint_name
                  AND tc.table_schema = kcu.table_schema
                 JOIN information_schema.constraint_column_usage ccu
                   ON ccu.constraint_name = tc.constraint_name
                  AND ccu.table_schema = tc.table_schema
                 WHERE tc.constraint_type = ?
                   AND tc.table_schema = current_schema()
                   AND tc.table_name = ?',
                ['FOREIGN KEY', self::TABLE]
            );

            $result = [];
            foreach ($rows as $row) {
                $result[$row->column_name] = [
                    'table' => $row->foreign_table_name,
                    'column' => $row->foreign_column_name,
                ];
            }

            return $result;
        }

        if ($driver === 'mysql') {
            $rows = $connection->select(
                'SELECT column_name,
                        referenced_table_name,
                        referenced_column_name
                 FROM information_schema.key_column_usage
                 WHERE table_schema = database()
                   AND table_name = ?
                   AND referenced_table_name IS NOT NULL',
                [self::TABLE]
            );

            $result = [];
            foreach ($rows as $row) {
                $result[$row->column_name] = [
                    'table' => $row->referenced_table_name,
                    'column' => $row->referenced_column_name,
                ];
            }

            return $result;
        }

        if ($driver === 'sqlite') {
            $rows = $connection->select("PRAGMA foreign_key_list('" . self::TABLE . "')");

            $result = [];
            foreach ($rows as $row) {
                $result[$row->from] = [
                    'table' => $row->table,
                    'column' => $row->to,
                ];
            }

            return $result;
        }

        return [];
    }
}
