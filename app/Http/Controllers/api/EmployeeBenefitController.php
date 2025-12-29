<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeBenefit;
use Illuminate\Http\Request;

class EmployeeBenefitController extends Controller
{
    protected function authorizeManage(Request $request)
    {
        $user = $request->user();
        if (! ($user && ($user->isAdmin() || $user->isHr()))) {
            abort(403, 'Only Admin or HR can manage benefits');
        }
    }

    protected function scopeVisible(Request $request)
    {
        $user = $request->user();
        if ($user->isAdmin() || $user->isHr()) {
            return EmployeeBenefit::query();
        }
        if (! $user->employee) {
            abort(403, 'No linked employee');
        }
        return EmployeeBenefit::where('employee_id', $user->employee->id);
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
            // Allow creating a benefit template without assigning to an employee yet
            'employee_id' => 'nullable|exists:employees,id',
            'benefit_name' => 'required|string|max:191',
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:fixed,percentage',
        ]);

        $benefit = EmployeeBenefit::create($data);
        return response()->json($benefit->fresh('employee'), 201);
    }

    public function show(Request $request, EmployeeBenefit $employeeBenefit)
    {
        $benefit = $this->scopeVisible($request)->where('id', $employeeBenefit->id)->firstOrFail();
        return $benefit->load('employee');
    }

    public function update(Request $request, EmployeeBenefit $employeeBenefit)
    {
        $this->authorizeManage($request);
        $data = $request->validate([
            'benefit_name' => 'sometimes|required|string|max:191',
            'amount' => 'sometimes|required|numeric|min:0',
            'type' => 'sometimes|required|in:fixed,percentage',
            'employee_id' => 'sometimes|nullable|exists:employees,id',
        ]);
        $employeeBenefit->update($data);
        return $employeeBenefit->fresh('employee');
    }

    public function destroy(Request $request, EmployeeBenefit $employeeBenefit)
    {
        $this->authorizeManage($request);
        $employeeBenefit->delete();
        return response()->noContent();
    }
}
