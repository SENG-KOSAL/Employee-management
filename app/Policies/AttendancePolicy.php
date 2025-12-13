<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendancePolicy
{
    use HandlesAuthorization;

    // Admin has all permissions
    public function before(User $user, $ability)
    {
        if ($user->isAdmin()) {
            return true;
        }
    }

    // View an attendance record
    public function view(User $user, Attendance $attendance): bool
    {
        // HR can view all
        if ($user->isHr()) {
            return true;
        }

        // Manager can view only their team
        if ($user->isManager()) {
            $managerEmployee = $user->employee;
            if (! $managerEmployee) return false;
            return $attendance->employee->line_manager_id === $managerEmployee->id;
        }

        // Employee can view only own attendance
        if ($user->isEmployee()) {
            return $user->employee && $user->employee->id === $attendance->employee_id;
        }

        return false;
    }

    // List / index checks: we will gate in controller using roles
    public function viewAny(User $user): bool
    {
        return $user->isAdmin() || $user->isHr() || $user->isManager() || $user->isEmployee();
    }

    // Admin/HR allowed to create manual records; employees use clock-in/out endpoints
    public function create(User $user): bool
    {
        return $user->isAdmin() || $user->isHr();
    }

    public function update(User $user, Attendance $attendance): bool
    {
        // Admin/HR can update; manager cannot modify records (unless you want to allow)
        return $user->isAdmin() || $user->isHr();
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->isAdmin() || $user->isHr();
    }
}