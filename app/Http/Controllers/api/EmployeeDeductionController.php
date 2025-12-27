<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeDeduction;
use Illuminate\Http\Request;

class EmployeeDeductionController extends Controller
{
    protected function authorizeManage(Request $request)
    {
        $user = $request->user();
        if (! ($user && ($user->isAdmin() || $user->isHr()))) {
            abort(403, 'Only Admin or HR can manage deductions');
        }
    }

    protected function scopeVisible(Request $request)
    {
        $user = $request->user();
        if ($user->isAdmin() || $user->isHr()) {
            return EmployeeDeduction::query();
        }
        if (! $user->employee) {
            abort(403, 'No linked employee');
        }
        return EmployeeDeduction::where('employee_id', $user->employee->id);
    }

    public function index(Request $request)
    {
        $query = $this->scopeVisible($request)->with('employee');
        if ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
        }
        return $query->orderBy('created_at', 'desc')->paginate(20);
    }

    public function store(Request $request)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            // Allow creating a deduction template without assigning to an employee yet
            'employee_id' => 'nullable|exists:employees,id',
            'deduction_name' => 'required|string|max:191',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:fixed,percentage',
            'reason' => 'nullable|string',
        ]);

        $deduction = EmployeeDeduction::create($data);
        return response()->json($deduction->fresh('employee'), 201);
    }

    public function show(Request $request, EmployeeDeduction $employeeDeduction)
    {
        $deduction = $this->scopeVisible($request)->where('id', $employeeDeduction->id)->firstOrFail();
        return $deduction->load('employee');
    }

    public function update(Request $request, EmployeeDeduction $employeeDeduction)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'deduction_name' => 'sometimes|required|string|max:191',
            'amount' => 'sometimes|required|numeric|min:0',
            'type' => 'sometimes|required|in:fixed,percentage',
            'reason' => 'nullable|string',
        ]);
        $employeeDeduction->update($data);
        return $employeeDeduction->fresh('employee');
    }

    public function destroy(Request $request, EmployeeDeduction $employeeDeduction)
    {
        $this->authorizeManage($request);
        $employeeDeduction->delete();
        return response()->noContent();
    }
}
