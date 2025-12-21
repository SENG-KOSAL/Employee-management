<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaveAllocation extends Model
{
    use HasFactory;

    protected $fillable = ['employee_id', 'leave_type_id', 'year', 'days_allocated', 'days_used'];

    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Get remaining days (allocated - used)
     */
    public function remaining(): float
    {
        return max(0.0, (float) $this->days_allocated - (float) $this->days_used);
    }
}