<?php

namespace App\Support;

use App\Models\Payslip;
use App\Models\Payroll;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class PayslipService
{
    public function generate(Payroll $payroll): Payslip
    {
        $payroll->loadMissing(['employee', 'employee.user']);

        $content = Pdf::loadView('pdf.payslip', [
            'payroll' => $payroll,
            'employee' => $payroll->employee,
            'company' => $payroll->company,
        ])->setPaper('a4');

        $path = sprintf(
            'payslips/%s/%s/%s-%s.pdf',
            $payroll->company_id,
            $payroll->period_start?->format('Y-m') ?? now()->format('Y-m'),
            $payroll->employee?->employee_code ?? $payroll->employee_id,
            $payroll->id
        );

        Storage::disk('private')->put($path, $content->output());

        return Payslip::updateOrCreate(
            ['payroll_id' => $payroll->id],
            [
                'company_id' => $payroll->company_id,
                'file_path' => $path,
                'generated_at' => now(),
                'delivery_status' => 'pending',
            ]
        );
    }
}
