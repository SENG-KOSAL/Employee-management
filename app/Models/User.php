<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    
    // Roles allowed in system
    public const ROLES = ['admin', 'hr', 'manager', 'employee'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'employee_id',
    ];

    /**
     * The attributes that should be hidden for arrays / JSON.
     *
     * @var array<int,string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string,string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Mutator: hash password automatically when set.
     */
    public function setPasswordAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['password'] = $value;
            return;
        }

        // Avoid double-hashing: check if already hashed
        if (Hash::needsRehash($value)) {
            $this->attributes['password'] = Hash::make($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }

    /**
     * Relationship: optional link to the employee record.
     * Many projects: one User <-> one Employee. This is nullable.
     */
    public function employee()
    {
        return $this->belongsTo(\App\Models\Employee::class, 'employee_id');
    }

    /**
     * Role helpers
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isHr(): bool
    {
        return $this->role === 'hr';
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    /**
     * Scope to filter by role.
     */
    public function scopeRole($query, $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Assign a role safely (validates role).
     */
    public function assignRole(string $role): void
    {
        if (! in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException("Invalid role: {$role}");
        }
        $this->role = $role;
        $this->save();
    }

    /**
     * Optionally unlink (detach) employee relation.
     */
    public function unlinkEmployee(): void
    {
        $this->employee_id = null;
        $this->save();
    }
}