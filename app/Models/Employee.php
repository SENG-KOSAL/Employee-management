<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'gender',
        'date_of_birth',
        'address',
        'department',
        'position',
        'department_id',
        'position_id',
        'base_salary',
        'start_date',
        'salary',
        'status',
        'line_manager_id',
        'photo_path',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'start_date' => 'date',
        'base_salary' => 'decimal:2',
        'salary' => 'decimal:2',
    ];

    // Accessor for full name
    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    // Public URL for photo
    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::url($this->photo_path) : null;
    }

    // Relationship to User account (optional, one-to-one)
    public function user()
    {
        return $this->hasOne(User::class, 'employee_id');
    }

    // Self-referential manager relationship
    public function lineManager()
    {
        return $this->belongsTo(self::class, 'line_manager_id');
    }

    // Employees who report to this employee
    public function directReports()
    {
        return $this->hasMany(self::class, 'line_manager_id');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveAllocations()
    {
        return $this->hasMany(LeaveAllocation::class);
    }

    public function overtimes()
    {
        return $this->hasMany(Overtime::class);
    }

    public function workScheduleAssignment()
    {
        return $this->hasOne(EmployeeWorkSchedule::class);
    }

    public function workSchedule()
    {
        return $this->hasOneThrough(
            WorkSchedule::class,
            EmployeeWorkSchedule::class,
            'employee_id',
            'id',
            'id',
            'work_schedule_id'
        );
    }

    public function benefits()
    {
        return $this->hasMany(EmployeeBenefit::class);
    }

    public function deductions()
    {
        return $this->hasMany(EmployeeDeduction::class);
    }
}