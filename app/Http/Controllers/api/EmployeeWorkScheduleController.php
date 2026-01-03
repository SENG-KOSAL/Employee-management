<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeWorkSchedule;
use Illuminate\Http\Request;

class EmployeeWorkScheduleController extends Controller
{
    public function store(Request $request)
    {
        $this->authorizeAdminHr();
        $data = $this->validateData($request);

        $assignment = EmployeeWorkSchedule::updateOrCreate(
            ['employee_id' => $data['employee_id']],
            [
                'work_schedule_id' => $data['work_schedule_id'],
                'effective_from' => $data['effective_from'],
            ]
        );

        return response()->json($assignment->load('workSchedule'), 201);
    }

    public function update(Request $request, EmployeeWorkSchedule $employeeWorkSchedule)
    {
        $this->authorizeAdminHr();
        $data = $this->validateData($request);
        $employeeWorkSchedule->update($data);
        return $employeeWorkSchedule->fresh('workSchedule');
    }

    public function destroy(EmployeeWorkSchedule $employeeWorkSchedule)
    {
        $this->authorizeAdminHr();
        $employeeWorkSchedule->delete();
        return response()->noContent();
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'work_schedule_id' => ['required', 'exists:work_schedules,id'],
            'effective_from' => ['required', 'date'],
        ]);
    }

    protected function authorizeAdminHr(): void
    {
        $user = request()->user();
        if (! $user || (! $user->isAdmin() && ! $user->isHr())) {
            abort(403, 'Admin or HR only');
        }
    }
}
