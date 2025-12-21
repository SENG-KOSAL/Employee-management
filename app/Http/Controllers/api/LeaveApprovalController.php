<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveLeaveRequest;
use App\Models\LeaveAllocation;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LeaveApprovalController extends Controller
{
    public function decide(ApproveLeaveRequest $request, LeaveRequest $leaveRequest)
    {
        $user = $request->user();

        // Permission: admin/hr or line manager (gate)
        if (! Gate::allows('approve-leave-for', $leaveRequest->employee)) {
            return response()->json(['message' => 'You are not authorized to approve/reject this leave'], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json(['message' => 'Leave already decided'], 400);
        }

        $data = $request->validated();
        $action = $data['action'];

        DB::transaction(function () use ($leaveRequest, $action, $data, $user) {
            if ($action === 'approve') {
                $leaveRequest->status = 'approved';
                $leaveRequest->approver_id = $user->id;
                $leaveRequest->approver_notes = $data['approver_notes'] ?? null;
                $leaveRequest->decided_at = Carbon::now();
                $leaveRequest->save();

                // Update allocation days_used (create allocation row if missing)
                $year = Carbon::parse($leaveRequest->start_date)->year;
                $allocation = LeaveAllocation::firstOrCreate(
                    [
                        'employee_id' => $leaveRequest->employee_id,
                        'leave_type_id' => $leaveRequest->leave_type_id,
                        'year' => $year,
                    ],
                    [
                        'days_allocated' => 0,
                        'days_used' => 0,
                    ]
                );

                $allocation->days_used = $allocation->days_used + $leaveRequest->days;
                $allocation->save();
            } else { // reject
                $leaveRequest->status = 'rejected';
                $leaveRequest->approver_id = $user->id;
                $leaveRequest->approver_notes = $data['approver_notes'] ?? null;
                $leaveRequest->decided_at = Carbon::now();
                $leaveRequest->save();
            }

            // Optionally: send notifications to employee
        });

        return new \App\Http\Resources\LeaveRequestResource($leaveRequest->fresh()->load(['employee','leaveType','approver']));
    }
}