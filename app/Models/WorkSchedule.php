<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'working_days',
        'hours_per_day',
    ];

    protected $casts = [
        'working_days' => 'array',
        'hours_per_day' => 'decimal:2',
    ];

    public function employeeAssignments()
    {
        return $this->hasMany(EmployeeWorkSchedule::class);
    }
}
