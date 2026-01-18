<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'period_start',
        'period_end',
        'base_pay',
        'overtime_pay',
        'benefits_total',
        'deductions_total',
        'unpaid_leave_deduction',
        'gross_pay',
        'net_pay',
        'status',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'payroll_run_id' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'base_pay' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'benefits_total' => 'decimal:2',
        'deductions_total' => 'decimal:2',
        'unpaid_leave_deduction' => 'decimal:2',
        'gross_pay' => 'decimal:2',
        'net_pay' => 'decimal:2',
        'paid_at' => 'date',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function run()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function payslip()
    {
        return $this->hasOne(Payslip::class);
    }
}
