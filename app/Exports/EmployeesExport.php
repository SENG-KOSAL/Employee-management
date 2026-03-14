<?php

namespace App\Exports;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EmployeesExport implements FromCollection, WithHeadings, WithMapping
{
    private array $columns;

    public function __construct(
        private readonly int $companyId,
        private readonly array $filters = []
    ) {
        $this->columns = (new Employee())->getFillable();
    }

    public function collection(): Collection
    {
        $query = Employee::query()->forCompany($this->companyId);

        $this->applyFilters($query);

        return $query
            ->orderBy('id')
            ->get($this->columns);
    }

    public function headings(): array
    {
        return $this->columns;
    }

    public function map($employee): array
    {
        return collect($this->columns)
            ->map(function (string $column) use ($employee) {
                $value = $employee->getAttribute($column);

                if ($value instanceof \DateTimeInterface) {
                    return $value->format('Y-m-d H:i:s');
                }

                return $value;
            })
            ->all();
    }

    private function applyFilters(Builder $query): void
    {
        $filterableColumns = ['department', 'position', 'status'];

        foreach ($filterableColumns as $column) {
            $value = $this->filters[$column] ?? null;

            if ($value !== null && $value !== '') {
                $query->where($column, $value);
            }
        }
    }
}