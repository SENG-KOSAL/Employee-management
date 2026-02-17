<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'status',
        'modules_enabled',
        'settings',
        'suspended_at',
    ];

    protected $casts = [
        'modules_enabled' => 'array',
        'settings' => 'array',
        'suspended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (Company $company) {
            if (! $company->slug) {
                $company->slug = Str::slug($company->name);
            }
        });
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function moduleFlags()
    {
        return $this->hasMany(ModuleFlag::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
