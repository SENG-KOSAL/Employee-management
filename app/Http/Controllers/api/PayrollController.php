<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr())) {
            abort(403, 'Forbidden');
        }

        $month = $request->query('month');
        $year = $request->query('year');
        $status = $request->query('status');
        $perPage = (int) $request->query('per_page', 15);

        $query = Payroll::with(['employee'])
            ->orderByDesc('period_start');

        if ($month && $year) {
            $periodStart = Carbon::createFromDate((int) $year, (int) $month, 1)->startOfDay();
            $periodEnd = $periodStart->copy()->endOfMonth();
            $query->whereDate('period_start', $periodStart->toDateString())
                  ->whereDate('period_end', $periodEnd->toDateString());
        }

        if ($status) {
            $query->where('status', $status);
        }

        $payload = $query->paginate($perPage)->withQueryString();

        return response()->json($payload);
    }

    public function show(Request $request, Payroll $payroll)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr())) {
            abort(403, 'Forbidden');
        }

        return $payroll->load(['employee', 'payslip']);
    }

    // Employee self: list own payrolls
    public function myPayrolls(Request $request)
    {
        $user = $request->user();
        if (! $user->isEmployee()) {
            abort(403, 'Forbidden');
        }

        $employee = $user->employee;
        if (! $employee) {
            abort(403, 'No linked employee');
        }

        $perPage = (int) $request->query('per_page', 15);

        $items = Payroll::where('employee_id', $employee->id)
            ->orderByDesc('period_start')
            ->paginate($perPage)
            ->withQueryString();

        return $items;
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr())) {
            abort(403, 'Forbidden');
        }

        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'notes' => 'nullable|string',
            'payroll_run_id' => 'nullable|exists:payroll_runs,id',
        ]);

        $periodStart = Carbon::createFromDate((int) $data['year'], (int) $data['month'], 1)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth();

        $employee = \App\Models\Employee::with([
            'benefits',
            'deductions',
            'workScheduleAssignment.workSchedule',
            'overtimes' => function ($q) use ($periodStart, $periodEnd) {
                $q->where('status', 'approved');
                $q->whereDate('work_date', '>=', $periodStart->toDateString());
                $q->whereDate('work_date', '<=', $periodEnd->toDateString());
            },
        ])->findOrFail($data['employee_id']);

        $summary = $this->calculateSalary($employee, $periodStart, $periodEnd);

        $payload = [
            'payroll_run_id' => $data['payroll_run_id'] ?? null,
            'employee_id' => $employee->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'base_pay' => $summary['base_salary'],
            'overtime_pay' => $summary['total_overtime'],
            'benefits_total' => $summary['total_benefits'],
            'deductions_total' => $summary['total_deductions'],
            'unpaid_leave_deduction' => $summary['unpaid_leave_deduction'],
            'gross_pay' => $summary['base_salary'] + $summary['total_benefits'] + $summary['total_overtime'],
            'net_pay' => $summary['net_salary'],
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
        ];

        $payroll = DB::transaction(function () use ($payload) {
            return Payroll::updateOrCreate(
                [
                    'employee_id' => $payload['employee_id'],
                    'period_start' => $payload['period_start'],
                    'period_end' => $payload['period_end'],
                ],
                $payload
            );
        });

        return response()->json($payroll->fresh(['employee']), 201);
    }

    public function markPaid(Request $request, Payroll $payroll)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr())) {
            abort(403, 'Forbidden');
        }

        $data = $request->validate([
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $payroll->status = 'paid';
        $payroll->paid_at = $data['paid_at'] ?? now();
        if (isset($data['notes'])) {
            $payroll->notes = $data['notes'];
        }
        $payroll->save();

        return $payroll->fresh(['employee']);
    }

    protected function calculateSalary(Employee $employee, Carbon $periodStart, Carbon $periodEnd): array
    {
        $schedule = optional($employee->workScheduleAssignment)->workSchedule;
        $workingDays = $schedule?->working_days ?? ['mon', 'tue', 'wed', 'thu', 'fri'];
        $hoursPerDay = (float) ($schedule?->hours_per_day ?? 8);

        $expectedWorkingDays = $this->calculateWorkingDaysInPeriod($periodStart, $periodEnd, $workingDays);

        $baseSalary = (float) ($employee->base_salary ?? $employee->salary ?? 0);
        $hourlyRate = $expectedWorkingDays > 0 && $hoursPerDay > 0
            ? $baseSalary / ($expectedWorkingDays * $hoursPerDay)
            : 0;

        $benefitTotal = 0;
        foreach ($employee->benefits as $benefit) {
            $benefitTotal += $benefit->type === 'percentage'
                ? $baseSalary * ((float) $benefit->amount / 100)
                : (float) $benefit->amount;
        }

        $deductionTotal = 0;
        foreach ($employee->deductions as $deduction) {
            $deductionTotal += $deduction->type === 'percentage'
                ? $baseSalary * ((float) $deduction->amount / 100)
                : (float) $deduction->amount;
        }

        $overtimeTotal = 0;
        if ($employee->relationLoaded('overtimes')) {
            foreach ($employee->overtimes as $ot) {
                $rateMultiplier = max((float) ($ot->rate ?? 1), 0);
                $overtimeTotal += (float) $ot->hours * $hourlyRate * $rateMultiplier;
            }
        }

        $unpaidLeave = 0; // placeholder for future implementation
        $net = $baseSalary + $benefitTotal + $overtimeTotal - $deductionTotal - $unpaidLeave;

        return [
            'base_salary' => $baseSalary,
            'hourly_rate' => $hourlyRate,
            'expected_working_days' => $expectedWorkingDays,
            'hours_per_day' => $hoursPerDay,
            'total_benefits' => $benefitTotal,
            'total_deductions' => $deductionTotal,
            'total_overtime' => $overtimeTotal,
            'unpaid_leave_deduction' => $unpaidLeave,
            'net_salary' => $net,
        ];
    }

    protected function calculateWorkingDaysInPeriod(Carbon $start, Carbon $end, array $workingDays): int
    {
        $normalized = array_map(fn ($d) => strtolower(substr($d, 0, 3)), $workingDays);
        $period = CarbonPeriod::create($start, $end);
        $count = 0;

        foreach ($period as $day) {
            if (in_array(strtolower($day->format('D')), $normalized, true)) {
                $count++;
            }
        }

        return $count;
    }
}
