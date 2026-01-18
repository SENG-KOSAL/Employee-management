<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveAllocation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaveAllocationController extends Controller
{
    // List allocations with optional filters
    public function index(Request $request)
    {
        $query = LeaveAllocation::with(['employee', 'leaveType']);

        if ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
        }
        if ($leaveTypeId = $request->query('leave_type_id')) {
            $query->where('leave_type_id', $leaveTypeId);
        }
        if ($year = $request->query('year')) {
            $query->where('year', $year);
        }

        return $query->orderByDesc('year')->orderBy('employee_id')->paginate((int) $request->query('per_page', 20));
    }

    // Create or assign allocation to an employee
    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'year' => 'nullable|integer|min:1970',
            'days_allocated' => 'required|numeric|min:0',
            'days_used' => 'nullable|numeric|min:0',
        ]);

        $data['year'] = $data['year'] ?? (int) date('Y');
        $data['days_used'] = $data['days_used'] ?? 0;

        // If an allocation for this employee/year/type exists, update it; otherwise create
        $allocation = DB::transaction(function () use ($data) {
            $alloc = LeaveAllocation::firstOrNew([
                'employee_id' => $data['employee_id'],
                'leave_type_id' => $data['leave_type_id'],
                'year' => $data['year'],
            ]);
            $alloc->days_allocated = $data['days_allocated'];
            $alloc->days_used = $data['days_used'];
            $alloc->save();
            return $alloc;
        });

        return $allocation->fresh(['employee', 'leaveType']);
    }

    // Show single allocation
    public function show(LeaveAllocation $leaveAllocation)
    {
        return $leaveAllocation->load(['employee', 'leaveType']);
    }

    // Update allocation (including reassign employee/type/year)
    public function update(Request $request, LeaveAllocation $leaveAllocation)
    {
        $data = $request->validate([
            'employee_id' => 'sometimes|required|exists:employees,id',
            'leave_type_id' => 'sometimes|required|exists:leave_types,id',
            'year' => 'sometimes|required|integer|min:1970',
            'days_allocated' => 'sometimes|required|numeric|min:0',
            'days_used' => 'sometimes|required|numeric|min:0',
        ]);

        $leaveAllocation->update($data);

        return $leaveAllocation->fresh(['employee', 'leaveType']);
    }

    public function destroy(LeaveAllocation $leaveAllocation)
    {
        $leaveAllocation->delete();
        return response()->noContent();
    }
}
