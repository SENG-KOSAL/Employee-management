<?php

namespace App\Imports;

use App\Models\Employee;
use App\Support\EmployeeImportSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EmployeeDynamicImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    private int $successCount = 0;

    private int $failedCount = 0;

    private array $errors = [];

    public function __construct(
        private readonly int $companyId,
        private readonly EmployeeImportSchema $schemaReader,
    ) {
    }

    public function collection(Collection $rows): void
    {
        $allowedColumns = $this->schemaReader->importableColumns();
        $requiredColumns = $this->schemaReader->requiredColumns();

        $foreignKeys = array_merge(
            $this->schemaReader->inferForeignKeysFromColumns($allowedColumns),
            $this->schemaReader->foreignKeys(),
        );

        foreach ($rows as $index => $row) {
            $excelRowNumber = $index + 2;
            $rowArray = $row->toArray();

            $payload = Arr::only($rowArray, $allowedColumns);
            $payload = $this->normalizeRow($payload);
            unset($payload['company_id']);

            $rules = $this->buildRules($payload, $allowedColumns, $requiredColumns, $foreignKeys);
            $validator = Validator::make($payload, $rules);

            if ($validator->fails()) {
                $this->recordFailure($excelRowNumber, $validator->errors()->all());
                continue;
            }

            $payload['company_id'] = $this->companyId;

            try {
                DB::transaction(function () use ($payload) {
                    Employee::create($payload);
                });

                $this->successCount++;
            } catch (\Throwable $exception) {
                $this->recordFailure($excelRowNumber, [$exception->getMessage()]);
            }
        }
    }

    public function results(): array
    {
        return [
            'success_count' => $this->successCount,
            'failed_count' => $this->failedCount,
            'errors' => $this->errors,
        ];
    }

    private function normalizeRow(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if ($value === '') {
                $payload[$key] = null;
            }
        }

        return $payload;
    }

    private function buildRules(array $payload, array $allowedColumns, array $requiredColumns, array $foreignKeys): array
    {
        $rules = [];

        foreach ($allowedColumns as $column) {
            $rules[$column] = in_array($column, $requiredColumns, true) ? ['required'] : ['nullable'];
        }

        if (array_key_exists('email', $rules)) {
            $rules['email'][] = 'email';
            $rules['email'][] = Rule::unique('employees', 'email')
                ->where(fn ($query) => $query->where('company_id', $this->companyId));
        }

        foreach ($foreignKeys as $column => $relation) {
            if (! array_key_exists($column, $rules)) {
                continue;
            }

            $table = $relation['table'] ?? null;
            $relatedColumn = $relation['column'] ?? 'id';

            if (! is_string($table) || ! Schema::hasTable($table)) {
                continue;
            }

            $exists = Rule::exists($table, $relatedColumn);

            if (Schema::hasColumn($table, 'company_id')) {
                $exists = $exists->where(fn ($query) => $query->where('company_id', $this->companyId));
            }

            $rules[$column][] = 'integer';
            $rules[$column][] = $exists;
        }

        return $rules;
    }

    private function recordFailure(int $row, array $messages): void
    {
        $this->failedCount++;
        $this->errors[] = [
            'row' => $row,
            'messages' => $messages,
        ];
    }
}
