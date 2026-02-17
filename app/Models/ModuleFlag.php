<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'module',
        'enabled',
        'enforced_at',
        'meta',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'enforced_at' => 'datetime',
        'meta' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
