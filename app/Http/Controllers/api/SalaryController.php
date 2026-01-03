<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;

class SalaryController extends Controller
{
    public function show(Request $request, Employee $employee)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr() || ($user->employee && $user->employee->id === $employee->id))) {
            abort(403, 'Forbidden');
        }

        $from = $request->query('from');
        $to = $request->query('to');

        $employee->load([
            'benefits',
            'deductions',
            'workScheduleAssignment.workSchedule',
            'overtimes' => function ($q) use ($from, $to) {
                $q->where('status', 'approved');
                if ($from) {
                    $q->whereDate('work_date', '>=', $from);
                }
                if ($to) {
                    $q->whereDate('work_date', '<=', $to);
                }
            },
        ]);

        $summary = $this->calculateSalary($employee, $from, $to);

        return response()->json([
            'employee_id' => $employee->id,
            'base_salary' => $summary['base_salary'],
            'total_benefits' => $summary['total_benefits'],
            'total_deductions' => $summary['total_deductions'],
            'total_overtime' => $summary['total_overtime'],
            'hourly_rate' => $summary['hourly_rate'],
            'expected_working_days' => $summary['expected_working_days'],
            'hours_per_day' => $summary['hours_per_day'],
            'unpaid_leave_deduction' => $summary['unpaid_leave_deduction'],
            'net_salary' => $summary['net_salary'],
            'breakdown' => $summary['breakdown'],
            'range' => [
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    protected function calculateSalary(Employee $employee, ?string $from, ?string $to): array
    {
        $periodStart = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $periodEnd = $to ? Carbon::parse($to)->endOfDay() : now()->endOfMonth();

        $schedule = optional($employee->workScheduleAssignment)->workSchedule;
        $workingDays = $schedule?->working_days ?? ['mon', 'tue', 'wed', 'thu', 'fri'];
        $hoursPerDay = (float) ($schedule?->hours_per_day ?? 8);

        $expectedWorkingDays = $this->calculateWorkingDaysInPeriod($periodStart, $periodEnd, $workingDays);

        $baseSalary = (float) ($employee->base_salary ?? $employee->salary ?? 0);
        $hourlyRate = $expectedWorkingDays > 0 && $hoursPerDay > 0
            ? $baseSalary / ($expectedWorkingDays * $hoursPerDay)
            : 0;

        $benefitTotal = 0;
        $benefitBreakdown = [];
        foreach ($employee->benefits as $benefit) {
            $amount = $benefit->type === 'percentage'
                ? $baseSalary * ((float) $benefit->amount / 100)
                : (float) $benefit->amount;
            $benefitTotal += $amount;
            $benefitBreakdown[] = [
                'name' => $benefit->benefit_name,
                'type' => $benefit->type,
                'amount' => $amount,
            ];
        }

        $deductionTotal = 0;
        $deductionBreakdown = [];
        foreach ($employee->deductions as $deduction) {
            $amount = $deduction->type === 'percentage'
                ? $baseSalary * ((float) $deduction->amount / 100)
                : (float) $deduction->amount;
            $deductionTotal += $amount;
            $deductionBreakdown[] = [
                'name' => $deduction->deduction_name,
                'type' => $deduction->type,
                'amount' => $amount,
                'reason' => $deduction->reason,
            ];
        }

        // OT: sum approved within loaded relation
        $overtimeTotal = 0;
        $overtimeBreakdown = [];
        if ($employee->relationLoaded('overtimes')) {
            foreach ($employee->overtimes as $ot) {
                $rateMultiplier = max((float) $ot->rate, 0);
                $amount = (float) $ot->hours * $hourlyRate * $rateMultiplier;
                $overtimeTotal += $amount;
                $overtimeBreakdown[] = [
                    'work_date' => $ot->work_date?->toDateString(),
                    'hours' => (float) $ot->hours,
                    'rate' => (float) $ot->rate,
                    'amount' => $amount,
                ];
            }
        }

        $unpaidLeave = 0; // hook for future unpaid leave calculation
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
            'breakdown' => [
                'benefits' => $benefitBreakdown,
                'deductions' => $deductionBreakdown,
                'overtime' => $overtimeBreakdown,
            ],
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
