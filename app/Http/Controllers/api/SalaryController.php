<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;

class SalaryController extends Controller
{
    public function show(Request $request, Employee $employee)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr() || ($user->employee && $user->employee->id === $employee->id))) {
            abort(403, 'Forbidden');
        }

        $employee->load(['benefits', 'deductions']);
        $summary = $this->calculateSalary($employee);

        return response()->json([
            'employee_id' => $employee->id,
            'salary' => $employee->salary,
            'total_benefits' => $summary['total_benefits'],
            'total_deductions' => $summary['total_deductions'],
            'net_salary' => $summary['net_salary'],
            'breakdown' => $summary['breakdown'],
        ]);
    }

    protected function calculateSalary(Employee $employee): array
    {
        $base = (float) ($employee->salary ?? 0);

        $benefitTotal = 0;
        $benefitBreakdown = [];
        foreach ($employee->benefits as $benefit) {
            $amount = $benefit->type === 'percentage'
                ? $base * ((float) $benefit->amount / 100)
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
                ? $base * ((float) $deduction->amount / 100)
                : (float) $deduction->amount;
            $deductionTotal += $amount;
            $deductionBreakdown[] = [
                'name' => $deduction->deduction_name,
                'type' => $deduction->type,
                'amount' => $amount,
                'reason' => $deduction->reason,
            ];
        }

        $net = $base + $benefitTotal - $deductionTotal;

        return [
            'total_benefits' => $benefitTotal,
            'total_deductions' => $deductionTotal,
            'net_salary' => $net,
            'breakdown' => [
                'benefits' => $benefitBreakdown,
                'deductions' => $deductionBreakdown,
            ],
        ];
    }
}
