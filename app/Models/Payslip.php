<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payslip extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'payroll_id',
        'file_path',
        'generated_at',
        'delivery_status',
        'delivery_channel',
        'delivered_at',
        'emailed_to',
        'last_error',
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}
