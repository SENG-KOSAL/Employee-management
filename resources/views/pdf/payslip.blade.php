<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        .header { margin-bottom: 16px; }
        .title { font-size: 20px; font-weight: bold; margin: 0; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f9fafb; }
        .totals td { font-weight: bold; }
    </style>
</head>
<body>
<div class="header">
    <p class="title">Payslip</p>
    <p class="muted">Company: {{ $company?->name ?? 'N/A' }}</p>
    <p class="muted">Employee: {{ $employee?->full_name ?? 'N/A' }} ({{ $employee?->employee_code ?? 'N/A' }})</p>
    <p class="muted">Period: {{ optional($payroll->period_start)->format('Y-m-d') }} to {{ optional($payroll->period_end)->format('Y-m-d') }}</p>
</div>

<table>
    <thead>
    <tr>
        <th>Item</th>
        <th>Amount</th>
    </tr>
    </thead>
    <tbody>
    <tr><td>Base Pay</td><td>{{ number_format((float)$payroll->base_pay, 2) }}</td></tr>
    <tr><td>Overtime Pay</td><td>{{ number_format((float)$payroll->overtime_pay, 2) }}</td></tr>
    <tr><td>Benefits Total</td><td>{{ number_format((float)$payroll->benefits_total, 2) }}</td></tr>
    <tr><td>Deductions Total</td><td>{{ number_format((float)$payroll->deductions_total, 2) }}</td></tr>
    <tr><td>Unpaid Leave Deduction</td><td>{{ number_format((float)$payroll->unpaid_leave_deduction, 2) }}</td></tr>
    </tbody>
    <tfoot class="totals">
    <tr><td>Gross Pay</td><td>{{ number_format((float)$payroll->gross_pay, 2) }}</td></tr>
    <tr><td>Net Pay</td><td>{{ number_format((float)$payroll->net_pay, 2) }}</td></tr>
    </tfoot>
</table>
</body>
</html>
