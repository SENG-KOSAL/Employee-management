<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'actor_user_id',
        'actor_company_id',
        'active_company_id',
        'action',
        'target_type',
        'target_id',
        'ip',
        'user_agent',
        'request_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorCompany()
    {
        return $this->belongsTo(Company::class, 'actor_company_id');
    }

    public function activeCompany()
    {
        return $this->belongsTo(Company::class, 'active_company_id');
    }
}
