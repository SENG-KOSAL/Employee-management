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
        'start_date',
        'salary',
        'status',
        'photo_path',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'start_date' => 'date',
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
}