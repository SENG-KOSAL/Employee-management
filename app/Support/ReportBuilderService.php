<?php

namespace App\Support;

use App\Exports\ReportArrayExport;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ReportBuilderService
{
    public function buildDataset(array $filters = []): array
    {
        $employeeQuery = Employee::query()->with('user');
        $leaveQuery = LeaveRequest::query()->with(['employee', 'leaveType']);
        $payrollQuery = Payroll::query()->with('employee');

        $this->applyFilters($filters, $employeeQuery, $leaveQuery, $payrollQuery);

        $employees = $employeeQuery->get();
        $leaves = $leaveQuery->get();
        $payrolls = $payrollQuery->get();

        return [
            'summary' => [
                'employees_total' => $employees->count(),
                'employees_active' => $employees->where('status', 'active')->count(),
                'employees_inactive' => $employees->where('status', 'inactive')->count(),
                'leave_total' => $leaves->count(),
                'leave_pending' => $leaves->where('status', 'pending')->count(),
                'leave_approved' => $leaves->where('status', 'approved')->count(),
                'leave_rejected' => $leaves->where('status', 'rejected')->count(),
                'payroll_total' => $payrolls->count(),
                'payroll_net_total' => (float) $payrolls->sum('net_pay'),
            ],
            'employees' => $employees,
            'leaves' => $leaves,
            'payrolls' => $payrolls,
        ];
    }

    public function export(array $dataset, string $format, string $diskPathPrefix = 'reports'): array
    {
        $timestamp = now()->format('Ymd_His');

        if ($format === 'pdf') {
            $path = sprintf('%s/report-%s.pdf', $diskPathPrefix, $timestamp);
            $pdf = Pdf::loadView('pdf.report-summary', ['dataset' => $dataset])->setPaper('a4');
            Storage::disk('private')->put($path, $pdf->output());

            return ['path' => $path, 'filename' => basename($path), 'mime' => 'application/pdf'];
        }

        $rows = [];
        foreach ($dataset['payrolls'] as $payroll) {
            $rows[] = [
                $payroll->id,
                $payroll->employee?->employee_code,
                $payroll->employee?->full_name,
                optional($payroll->period_start)->format('Y-m-d'),
                optional($payroll->period_end)->format('Y-m-d'),
                (float) $payroll->gross_pay,
                (float) $payroll->net_pay,
                $payroll->status,
            ];
        }

        $headings = ['Payroll ID', 'Employee Code', 'Employee Name', 'Period Start', 'Period End', 'Gross Pay', 'Net Pay', 'Status'];

        if ($format === 'excel') {
            $path = sprintf('%s/report-%s.xlsx', $diskPathPrefix, $timestamp);
            Excel::store(new ReportArrayExport($headings, $rows), $path, 'private');

            return ['path' => $path, 'filename' => basename($path), 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        }

        $path = sprintf('%s/report-%s.csv', $diskPathPrefix, $timestamp);
        $lines = [implode(',', $headings)];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(function ($value) {
                $v = (string) $value;
                $v = str_replace('"', '""', $v);
                return '"' . $v . '"';
            }, $row));
        }

        Storage::disk('private')->put($path, implode("\n", $lines));

        return ['path' => $path, 'filename' => basename($path), 'mime' => 'text/csv'];
    }

    private function applyFilters(array $filters, $employeeQuery, $leaveQuery, $payrollQuery): void
    {
        if (! empty($filters['period_start']) && ! empty($filters['period_end'])) {
            $start = Carbon::parse($filters['period_start'])->toDateString();
            $end = Carbon::parse($filters['period_end'])->toDateString();

            $leaveQuery->whereDate('start_date', '>=', $start)->whereDate('end_date', '<=', $end);
            $payrollQuery->whereDate('period_start', '>=', $start)->whereDate('period_end', '<=', $end);
        }

        if (! empty($filters['department'])) {
            $employeeQuery->where('department', $filters['department']);
            $leaveQuery->whereHas('employee', fn ($q) => $q->where('department', $filters['department']));
            $payrollQuery->whereHas('employee', fn ($q) => $q->where('department', $filters['department']));
        }

        if (! empty($filters['role'])) {
            $employeeQuery->whereHas('user', fn ($q) => $q->where('role', $filters['role']));
        }

        if (! empty($filters['status'])) {
            $employeeQuery->where('status', $filters['status']);
            $leaveQuery->where('status', $filters['status']);
            $payrollQuery->where('status', $filters['status']);
        }

        if (! empty($filters['manager'])) {
            $employeeQuery->where('line_manager_id', $filters['manager']);
            $leaveQuery->whereHas('employee', fn ($q) => $q->where('line_manager_id', $filters['manager']));
            $payrollQuery->whereHas('employee', fn ($q) => $q->where('line_manager_id', $filters['manager']));
        }
    }
}
