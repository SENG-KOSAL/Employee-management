<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PayrollPeriod extends Model
{
    use HasFactory;
    use BelongsToCompany;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'start_date',
        'end_date',
        'payment_date',
        'is_locked',
        'locked_at',
        'locked_by',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'payment_date' => 'date',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function locker()
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
