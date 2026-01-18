<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overtime extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'work_date',
        'hours',
        'rate',
        'status',
        'approved_by',
        'approved_at',
        'notes',
    ];

    protected $casts = [
        'work_date' => 'date',
        'hours' => 'decimal:2',
        'rate' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}




