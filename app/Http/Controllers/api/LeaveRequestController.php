<?php

namespace App\Http\Controllers\Api;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = LeaveRequest::with(['leaveType', 'employee', 'approver']);

        // Role scoping
        if ($user->isAdmin() || $user->isHr()) {
            // can view all; optionally filter by employee_id
            if ($employeeId = $request->query('employee_id')) {
                $query->where('employee_id', $employeeId);
            }
        } elseif ($user->isManager()) {
            $managerEmployee = $user->employee;
            if (! $managerEmployee) return response()->json(['message' => 'No linked manager employee record'], 403);
            $query->whereHas('employee', function ($q) use ($managerEmployee) {
                $q->where('line_manager_id', $managerEmployee->id);
            });
        } else { // employee
            $employee = $user->employee;
            if (! $employee) return response()->json(['message' => 'No linked employee'], 403);
            $query->where('employee_id', $employee->id);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $perPage = (int) $request->query('per_page', 15);
        $items = $query->orderBy('created_at', 'desc')->paginate($perPage)->withQueryString();

        return LeaveRequestResource::collection($items);
    }

    public function store(StoreLeaveRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();

        // Determine employee_id: if not provided, use current user's employee
        if (empty($data['employee_id'])) {
            $employee = $user->employee;
            if (! $employee) return response()->json(['message' => 'No linked employee record'], 403);
            $data['employee_id'] = $employee->id;
        } else {
            // If creating for another employee, only Admin/HR allowed
            if (! ($user->isAdmin() || $user->isHr())) {
                return response()->json(['message' => 'Forbidden to create leave for another employee'], 403);
            }
        }

        // Overlapping leave prevention: any pending or approved leave that overlaps
        $overlap = LeaveRequest::where('employee_id', $data['employee_id'])
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($data) {
                $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                  ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']])
                  ->orWhereRaw('? BETWEEN start_date AND end_date', [$data['start_date']])
                  ->orWhereRaw('? BETWEEN start_date AND end_date', [$data['end_date']]);
            })->exists();

        if ($overlap) {
            return response()->json(['message' => 'Overlapping leave exists for the requested dates'], 422);
        }

        // Compute days if caller provided days or compute automatically from dates
        // We'll accept provided days but if not provided compute working days
        if (empty($data['days'])) {
            $data['days'] = $this->computeWorkingDays($data['start_date'], $data['end_date']);
        }

        // Check leave allocation/balance for that year
        $year = Carbon::parse($data['start_date'])->year;
        $alloc = LeaveAllocation::where('employee_id', $data['employee_id'])
            ->where('leave_type_id', $data['leave_type_id'])
            ->where('year', $year)
            ->first();

        $allocated = $alloc ? (float) $alloc->days_allocated : 0.0;
        // compute used from approved leaves (in case allocation.days_used is not kept)
        $used = LeaveRequest::where('employee_id', $data['employee_id'])
            ->where('leave_type_id', $data['leave_type_id'])
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->sum('days');

        $remaining = max(0.0, $allocated - (float)$used);

        if ($data['days'] > $remaining) {
            return response()->json([
                'message' => 'Insufficient leave balance',
                'remaining' => $remaining,
            ], 422);
        }

        $leave = LeaveRequest::create($data);

        // Optionally: notify manager/HR via Notification (not included here)

        return new LeaveRequestResource($leave->load(['leaveType', 'employee']));
    }

    public function show(LeaveRequest $leaveRequest)
    {
        $user = request()->user();

        if ($user->isAdmin() || $user->isHr()) {
            // allowed
        } elseif ($user->isManager()) {
            $managerEmployee = $user->employee;
            if (! $managerEmployee || $leaveRequest->employee->line_manager_id !== $managerEmployee->id) {
                abort(403, 'Forbidden');
            }
        } else {
            // employee self only
            if (! ($user->employee && $user->employee->id === $leaveRequest->employee_id)) {
                abort(403, 'Forbidden');
            }
        }

        return new LeaveRequestResource($leaveRequest->load(['leaveType', 'employee', 'approver']));
    }

    // Employee can cancel pending request
    public function cancel(Request $request, LeaveRequest $leaveRequest)
    {
        $user = $request->user();

        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Only pending requests can be cancelled'], 400);
        }

        if ($user->isAdmin() || $user->isHr()) {
            // allowed to cancel
        } else {
            // employee can only cancel their own
            if (! ($user->employee && $user->employee->id === $leaveRequest->employee_id)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $leaveRequest->status = 'cancelled';
        $leaveRequest->save();

        return new LeaveRequestResource($leaveRequest);
    }

    /**
     * Compute working days between two dates, default excludes weekends.
     * If you need to exclude holidays, extend this to consult a holidays table.
     */
    protected function computeWorkingDays($start, $end, $excludeWeekends = true): float
    {
        $start = Carbon::parse($start)->startOfDay();
        $end = Carbon::parse($end)->startOfDay();

        if ($start->gt($end)) return 0;

        $days = 0;
        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            if ($excludeWeekends && in_array($date->dayOfWeek, [Carbon::SATURDAY, Carbon::SUNDAY], true)) {
                continue;
            }
            $days++;
        }

        return (float) $days;
    }
}