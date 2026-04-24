<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'month',
        'year',
        'period_start',
        'period_end',
        'payment_date',
        'status',
        'created_by',
        'approved_by',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'month' => 'integer',
        'year' => 'integer',
        'period_start' => 'date',
        'period_end' => 'date',
        'payment_date' => 'date',
        'paid_at' => 'date',
    ];

    public function payrolls()
    {
        return $this->hasMany(Payroll::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
