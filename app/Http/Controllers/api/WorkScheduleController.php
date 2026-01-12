<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkSchedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WorkScheduleController extends Controller
{
    public function index()
    {
        $this->authorizeAdminHr();
        return WorkSchedule::orderBy('name')->paginate(20);
    }

    public function store(Request $request)
    {
        $this->authorizeAdminHr();
        $data = $this->validateData($request);
        $schedule = WorkSchedule::create($data);
        return response()->json($schedule, 201);
    }

    public function show(WorkSchedule $workSchedule)
    {
        $this->authorizeAdminHr();
        return $workSchedule;
    }

    public function update(Request $request, WorkSchedule $workSchedule)
    {
        $this->authorizeAdminHr();
        $data = $this->validateData($request);
        $workSchedule->update($data);
        return $workSchedule->fresh();
    }

    public function destroy(WorkSchedule $workSchedule)
    {
        $this->authorizeAdminHr();
        $workSchedule->delete();
        return response()->noContent();
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => [
                'string',
                Rule::in(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']),
            ],
            'hours_per_day' => ['required', 'numeric', 'min:0.25', 'max:24'],
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
