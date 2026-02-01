<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
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

        return $payroll->load(['employee', 'payslip', 'adjustments', 'auditLogs.user']);
    }

    public function update(Request $request, Payroll $payroll)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr())) {
            abort(403, 'Forbidden');
        }

        if ($payroll->status !== 'draft') {
            abort(422, 'Only draft payroll can be edited');
        }

        $data = $request->validate([
            'base_pay' => 'sometimes|numeric|min:0',
            'overtime_pay' => 'sometimes|numeric|min:0',
            'benefits_total' => 'sometimes|numeric|min:0',
            'deductions_total' => 'sometimes|numeric|min:0',
            'unpaid_leave_deduction' => 'sometimes|numeric|min:0',
            'notes' => 'sometimes|nullable|string',
        ]);

        $trackedFields = [
            'base_pay',
            'overtime_pay',
            'benefits_total',
            'deductions_total',
            'unpaid_leave_deduction',
            'gross_pay',
            'net_pay',
            'notes',
        ];

        return DB::transaction(function () use ($payroll, $data, $user, $trackedFields) {
            $before = $payroll->only($trackedFields);

            foreach ($data as $key => $value) {
                $payroll->{$key} = $value;
            }

            $basePay = (float) $payroll->base_pay;
            $overtimePay = (float) $payroll->overtime_pay;
            $benefitsTotal = (float) $payroll->benefits_total;
            $deductionsTotal = (float) $payroll->deductions_total;
            $unpaidLeaveDeduction = (float) $payroll->unpaid_leave_deduction;

            $grossPay = $basePay + $overtimePay + $benefitsTotal;
            $netPay = $grossPay - $deductionsTotal - $unpaidLeaveDeduction;

            $payroll->gross_pay = $grossPay;
            $payroll->net_pay = $netPay;
            $payroll->save();

            $after = $payroll->fresh()->only($trackedFields);

            $payroll->auditLogs()->create([
                'user_id' => $user->id,
                'action' => 'edited',
                'changes' => [
                    'before' => $before,
                    'after' => $after,
                ],
            ]);

            return $payroll->fresh(['employee', 'adjustments', 'auditLogs.user']);
        });
    }

    public function listAdjustments(Request $request, Payroll $payroll)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr())) {
            abort(403, 'Forbidden');
        }

        return $payroll->adjustments()->orderByDesc('created_at')->get();
    }

    public function createAdjustment(Request $request, Payroll $payroll)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr())) {
            abort(403, 'Forbidden');
        }

        if (! in_array($payroll->status, ['approved', 'paid'], true)) {
            abort(422, 'Adjustments are allowed only for approved or paid payrolls');
        }

        $data = $request->validate([
            'kind' => 'required|in:earning,deduction',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'nullable|string|max:255',
        ]);

        $trackedFields = [
            'benefits_total',
            'deductions_total',
            'gross_pay',
            'net_pay',
        ];

        return DB::transaction(function () use ($payroll, $data, $user, $trackedFields) {
            $before = $payroll->only($trackedFields);

            $adjustment = $payroll->adjustments()->create([
                'kind' => $data['kind'],
                'amount' => $data['amount'],
                'description' => $data['description'] ?? null,
                'created_by' => $user->id,
            ]);

            $amount = (float) $adjustment->amount;
            if ($adjustment->kind === 'earning') {
                $payroll->benefits_total = (float) $payroll->benefits_total + $amount;
            } else {
                $payroll->deductions_total = (float) $payroll->deductions_total + $amount;
            }

            $basePay = (float) $payroll->base_pay;
            $overtimePay = (float) $payroll->overtime_pay;
            $benefitsTotal = (float) $payroll->benefits_total;
            $deductionsTotal = (float) $payroll->deductions_total;
            $unpaidLeaveDeduction = (float) $payroll->unpaid_leave_deduction;

            $grossPay = $basePay + $overtimePay + $benefitsTotal;
            $netPay = $grossPay - $deductionsTotal - $unpaidLeaveDeduction;

            $payroll->gross_pay = $grossPay;
            $payroll->net_pay = $netPay;
            $payroll->save();

            $after = $payroll->fresh()->only($trackedFields);

            $payroll->auditLogs()->create([
                'user_id' => $user->id,
                'action' => 'adjustment_created',
                'changes' => [
                    'adjustment' => $adjustment->only(['id', 'kind', 'amount', 'description']),
                    'before' => $before,
                    'after' => $after,
                ],
            ]);

            return response()->json([
                'payroll' => $payroll->fresh(['employee', 'adjustments']),
                'adjustment' => $adjustment->fresh(['creator']),
            ], 201);
        });
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
