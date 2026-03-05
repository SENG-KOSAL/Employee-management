<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class EmployeesTemplateExport implements FromArray, WithHeadings
{
    public function __construct(private readonly array $headings)
    {
    }

    public function array(): array
    {
        return [];
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
