<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Overtime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;

class OvertimeController extends Controller
{
    protected function canManageEmployee($user, int $employeeId): bool
    {
        if ($user->isAdmin() || $user->isHr()) {
            return true;
        }
        $emp = $user->employee;
        return $user->isManager() && $emp && Employee::where('id', $employeeId)->where('line_manager_id', $emp->id)->exists();
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = Overtime::with(['employee', 'approver']);

        if ($user->isAdmin() || $user->isHr()) {
            // all
        } elseif ($user->isManager()) {
            $managerEmp = $user->employee;
            if ($managerEmp) {
                $query->whereHas('employee', function ($q) use ($managerEmp) {
                    $q->where('line_manager_id', $managerEmp->id);
                });
            } else {
                return response()->json(['message' => 'No linked manager employee record'], 403);
            }
        } else {
            $emp = $user->employee;
            if (! $emp) return response()->json(['message' => 'No linked employee'], 403);
            $query->where('employee_id', $emp->id);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('work_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('work_date', '<=', $to);
        }

        return $query->orderByDesc('work_date')->paginate((int) $request->query('per_page', 20));
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $isAdminOrHr = $user->isAdmin() || $user->isHr();
        $data = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'work_date' => 'required|date',
            'hours' => 'required|numeric|min:0.25',
            'rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if (empty($data['employee_id'])) {
            $emp = $user->employee;
            if (! $emp) return response()->json(['message' => 'No linked employee'], 403);
            $data['employee_id'] = $emp->id;
        } else {
            if (! ($isAdminOrHr || $this->canManageEmployee($user, (int) $data['employee_id']))) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $data['rate'] = $data['rate'] ?? 1; // default multiplier (1x)

        if ($isAdminOrHr) {
            $data['status'] = 'approved';
            $data['approved_by'] = $user->id;
            $data['approved_at'] = now();
        } else {
            $data['status'] = 'pending';
        }

        $ot = Overtime::create($data);
        return $ot->load(['employee']);
    }

    public function show(Overtime $overtime)
    {
        return $overtime->load(['employee', 'approver']);
    }

    public function update(Request $request, Overtime $overtime)
    {
        $user = $request->user();
        // Only pending can be edited
        if ($overtime->status !== 'pending') {
            return response()->json(['message' => 'Only pending OT can be edited'], 400);
        }
        if (! ($user->isAdmin() || $user->isHr() || $this->canManageEmployee($user, $overtime->employee_id) || ($user->employee && $user->employee->id === $overtime->employee_id))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'work_date' => 'sometimes|required|date',
            'hours' => 'sometimes|required|numeric|min:0.25',
            'rate' => 'sometimes|nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        if (array_key_exists('rate', $data) && $data['rate'] === null) {
            $data['rate'] = 1; // normalize null to 1x
        }

        $overtime->update($data);
        return $overtime->fresh(['employee', 'approver']);
    }

    public function destroy(Request $request, Overtime $overtime)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr() || $this->canManageEmployee($user, $overtime->employee_id) || ($overtime->status === 'pending' && $user->employee && $user->employee->id === $overtime->employee_id))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($overtime->status !== 'pending' && ! ($user->isAdmin() || $user->isHr())) {
            return response()->json(['message' => 'Only Admin/HR can delete approved/rejected OT'], 403);
        }
        $overtime->delete();
        return response()->noContent();
    }

    public function approve(Request $request, Overtime $overtime)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr() || $this->canManageEmployee($user, $overtime->employee_id))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($overtime->status !== 'pending') {
            return response()->json(['message' => 'Only pending OT can be approved'], 400);
        }

        $overtime->status = 'approved';
        $overtime->approved_by = $user->id;
        $overtime->approved_at = now();
        $overtime->save();

        return $overtime->fresh(['employee', 'approver']);
    }

    public function reject(Request $request, Overtime $overtime)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr() || $this->canManageEmployee($user, $overtime->employee_id))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        if ($overtime->status !== 'pending') {
            return response()->json(['message' => 'Only pending OT can be rejected'], 400);
        }

        $data = $request->validate([
            'notes' => 'nullable|string',
        ]);

        $overtime->status = 'rejected';
        $overtime->approved_by = $user->id;
        $overtime->approved_at = now();
        if (isset($data['notes'])) {
            $overtime->notes = $data['notes'];
        }
        $overtime->save();

        return $overtime->fresh(['employee', 'approver']);
    }
}
