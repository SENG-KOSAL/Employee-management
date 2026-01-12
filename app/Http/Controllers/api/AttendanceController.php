<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClockInRequest;
use App\Http\Requests\ClockOutRequest;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\Employee;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AttendanceController extends Controller
{
    public function __construct()
    {
        // ensure auth on these routes
        // $this->middleware('auth:sanctum');
    }

    // Employee clock-in
    public function clockIn(ClockInRequest $request)
    {
        $user = $request->user();
        $employee = $this->resolveEmployee($request, $user);

        // find or create today's attendance record
        $today = Carbon::now()->toDateString();

        $attendance = Attendance::firstOrNew([
            'employee_id' => $employee->id,
            'date' => $today,
        ]);

        if ($attendance->check_in) {
            return response()->json(['message' => 'Already checked in for today'], 400);
        }

        $now = Carbon::now();
        $attendance->check_in = $now;

        // determine lateness
        $shiftStart = $request->input('shift_start')
            ? Carbon::parse($request->input('shift_start'))
            : $now->copy()->setTime(9, 0);

        $attendance->is_late = $now->greaterThan($shiftStart);
        $attendance->attendance_status = 'present';
        $attendance->save();

        return new AttendanceResource($attendance);
    }

    // Employee clock-out
    public function clockOut(ClockOutRequest $request)
    {
        $user = $request->user();
        $employee = $this->resolveEmployee($request, $user);

        $today = Carbon::now()->toDateString();

        $attendance = Attendance::where('employee_id', $employee->id)->where('date', $today)->first();

        if (! $attendance || ! $attendance->check_in) {
            return response()->json(['message' => 'No check-in found for today'], 400);
        }

        if ($attendance->check_out) {
            return response()->json(['message' => 'Already checked out for today'], 400);
        }

        $now = Carbon::now();
        $attendance->check_out = $now;

        $duration = $now->floatDiffInHours($attendance->check_in);
        $attendance->total_hours = round($duration, 2);
        $attendance->overtime_hours = max(0, $attendance->total_hours - 8);
        $attendance->save();

        return new AttendanceResource($attendance);
    }

    // Index with filters and role-based scoping
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Attendance::query()->with('employee');

        // Date filters
        if ($from = $request->query('from')) {
            $query->where('date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('date', '<=', $to);
        }

        // employee filter
        if ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        // Role scoping
        if ($user->isAdmin() || $user->isHr()) {
            // full access to all matches
        } elseif ($user->isManager()) {
            $managerEmployee = $user->employee;
            if (! $managerEmployee) return response()->json(['message' => 'No linked employee for manager'], 403);
            $query->whereHas('employee', function ($q) use ($managerEmployee) {
                $q->where('line_manager_id', $managerEmployee->id);
            });
        } else { // employee
            $employee = $user->employee;
            if (! $employee) return response()->json(['message' => 'No linked employee'], 403);
            $query->where('employee_id', $employee->id);
            // ignore provided employee_id to enforce self-only
        }
        }

        // Pagination
        $perPage = (int) $request->query('per_page', 15);
        $items = $query->orderBy('date', 'desc')->paginate($perPage)->withQueryString();

        return AttendanceResource::collection($items);
    }

    // Summary endpoint: daily|weekly|monthly grouped totals
    public function summary(Request $request)
    {
        $user = $request->user();
        $type = $request->query('type', 'daily'); // daily/weekly/monthly
        $from = $request->query('from');
        $to = $request->query('to');
        $tz = 'Asia/Phnom_Penh';

        $query = Attendance::query();

        // Role scoping (same as index)
        if ($user->isManager()) {
            $managerEmployee = $user->employee;
            if (! $managerEmployee) return response()->json(['message' => 'No linked employee for manager'], 403);
            $query->whereHas('employee', function ($q) use ($managerEmployee) {
                $q->where('line_manager_id', $managerEmployee->id);
            });
        } elseif ($user->isEmployee()) {
            $employee = $user->employee;
            if (! $employee) return response()->json(['message' => 'No linked employee'], 403);
            $query->where('employee_id', $employee->id);
        }

        if ($from) $query->where('date', '>=', $from);
        if ($to) $query->where('date', '<=', $to);

        if ($type === 'weekly') {
            $results = $query->selectRaw("employee_id, date_trunc('week', date) as period, sum(total_hours) as total_hours, sum(overtime_hours) as overtime_hours")
                ->groupBy('employee_id', 'period')
                ->orderBy('period', 'desc')
                ->get();
        } elseif ($type === 'monthly') {
            $results = $query->selectRaw("employee_id, date_trunc('month', date) as period, sum(total_hours) as total_hours, sum(overtime_hours) as overtime_hours")
                ->groupBy('employee_id', 'period')
                ->orderBy('period', 'desc')
                ->get();
        } else { // daily
            $results = $query->selectRaw("employee_id, date as period, sum(total_hours) as total_hours, sum(overtime_hours) as overtime_hours")
                ->groupBy('employee_id', 'period')
                ->orderBy('period', 'desc')
                ->get();
        }

        // $mapped = $results->map(function ($row) use ($tz) {
        //     $periodUtc = Carbon::parse($row->period, 'UTC');
        //     return [
        //         'employee_id' => $row->employee_id,
        //         'period' => $periodUtc->toDateString(),
        //         'period_iso_local' => $periodUtc->setTimezone($tz)->toIso8601String(),
        //         'timezone' => $tz,
        //         'total_hours' => $row->total_hours !== null ? (float) $row->total_hours : null,
        //         'overtime_hours' => $row->overtime_hours !== null ? (float) $row->overtime_hours : null,
        //     ];
        // });
        $tz = 'Asia/Phnom_Penh';

        $mapped = $results->map(function ($row) use ($tz) {

            $utc = Carbon::parse($row->period, 'UTC');
            $local = $utc->setTimezone($tz);

            return [
                'employee_id' => $row->employee_id,
                'period' => $local->format('Y-m-d H:i:s'),
                'period_iso_local' => $local->toIso8601String(),
                'timezone' => $tz,
                'total_hours' => (float) $row->total_hours,
                'overtime_hours' => (float) $row->overtime_hours,
            ];
        });
        return response()->json($mapped);
    }

    // Helper: resolve employee for current user; admin/HR may pass employee_id
    protected function resolveEmployee(Request $request, $user): Employee
    {
        if ($request->filled('employee_id')) {
            // only allow admin/hr to act for other employees
            if (! ($user->isAdmin() || $user->isHr())) {
                abort(403, 'Forbidden: cannot act for other employee');
            }
            return Employee::findOrFail($request->input('employee_id'));
        }

        $employee = $user->employee;
        if (! $employee) {
            abort(403, 'No linked employee record');
        }
        return $employee;
    }
}
