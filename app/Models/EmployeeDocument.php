<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeDocument extends Model
{
    use HasFactory;
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'employee_id',
        'id_card_file_path',
        'contract_file_path',
        'cv_file_path',
        'certificate_file_path',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
