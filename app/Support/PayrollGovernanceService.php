<?php

namespace App\Support;

use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriodLock;

class PayrollGovernanceService
{
    public function isLocked(Payroll $payroll): bool
    {
        return PayrollPeriodLock::query()
            ->whereDate('period_start', $payroll->period_start)
            ->whereDate('period_end', $payroll->period_end)
            ->exists();
    }

    public function evaluateExceptionFlags(Payroll $payroll, PayrollAdjustment $adjustment): array
    {
        $amount = (float) $adjustment->amount;
        $projectedBenefits = (float) $payroll->benefits_total;
        $projectedDeductions = (float) $payroll->deductions_total;

        if ($adjustment->kind === 'earning') {
            $projectedBenefits += $amount;
        } else {
            $projectedDeductions += $amount;
        }

        $gross = (float) $payroll->base_pay + (float) $payroll->overtime_pay + $projectedBenefits;
        $projectedNet = $gross - $projectedDeductions - (float) $payroll->unpaid_leave_deduction;

        $maxOvertimeRatio = (float) config('payroll_governance.max_overtime_ratio', 0.6);
        $maxDeductionRatio = (float) config('payroll_governance.max_deduction_ratio', 0.7);

        $flags = [];

        if ($projectedNet < 0) {
            $flags[] = 'negative_net_pay';
        }

        $base = max((float) $payroll->base_pay, 0.01);
        if (((float) $payroll->overtime_pay / $base) > $maxOvertimeRatio) {
            $flags[] = 'excessive_overtime';
        }

        $grossNonZero = max($gross, 0.01);
        if (($projectedDeductions / $grossNonZero) > $maxDeductionRatio) {
            $flags[] = 'deduction_anomaly';
        }

        return array_values(array_unique($flags));
    }
}
