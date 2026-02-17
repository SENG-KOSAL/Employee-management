<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginAsCompanyEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'super_admin_id',
        'company_id',
        'entered_at',
        'exited_at',
        'reason',
        'audit_trail',
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'exited_at' => 'datetime',
        'audit_trail' => 'array',
    ];

    public function superAdmin()
    {
        return $this->belongsTo(User::class, 'super_admin_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
