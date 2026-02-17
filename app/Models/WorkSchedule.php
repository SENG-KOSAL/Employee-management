<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
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
