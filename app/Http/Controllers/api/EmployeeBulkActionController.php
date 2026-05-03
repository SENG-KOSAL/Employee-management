<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class EmployeeBulkActionController extends Controller
{
	public function __construct(private readonly AuditLogger $auditLogger)
	{
	}

	public function handle(Request $request)
	{
		$validated = $request->validate([
			'action' => ['required', 'string', Rule::in([
				'delete',
				'update_status',
				'assign_department',
				'assign_position',
				'assign_manager',
			])],
			'employee_ids' => ['required', 'array', 'min:1'],
			'employee_ids.*' => ['integer', 'distinct'],
			'status' => ['nullable', 'string'],
			'department' => ['nullable', 'string'],
			'department_id' => ['nullable', 'integer'],
			'position' => ['nullable', 'string'],
			'position_id' => ['nullable', 'integer'],
			'line_manager_id' => ['nullable', 'integer'],
		]);

		$action = $validated['action'];
		$employeeIds = $validated['employee_ids'];

		$employees = Employee::query()->whereIn('id', $employeeIds)->get();
		if ($employees->isEmpty()) {
			return response()->json([
				'message' => 'No employees found for the provided IDs.',
			], 404);
		}

		return DB::transaction(function () use ($request, $employees, $employeeIds, $action, $validated) {
			$affected = 0;

			switch ($action) {
				case 'delete':
					foreach ($employees as $employee) {
						if ($employee->photo_path) {
							Storage::disk('public')->delete($employee->photo_path);
						}
						$employee->delete();
						$affected++;
					}
					break;

				case 'update_status':
					$status = $validated['status'] ?? null;
					if ($status === null || $status === '') {
						return response()->json([
							'message' => 'Status is required for update_status action.',
						], 422);
					}
					$affected = Employee::query()->whereIn('id', $employeeIds)->update([
						'status' => $status,
					]);
					break;

				case 'assign_department':
					if (empty($validated['department']) && empty($validated['department_id'])) {
						return response()->json([
							'message' => 'Department or department_id is required for assign_department action.',
						], 422);
					}
					$affected = Employee::query()->whereIn('id', $employeeIds)->update([
						'department' => $validated['department'] ?? null,
						'department_id' => $validated['department_id'] ?? null,
					]);
					break;

				case 'assign_position':
					if (empty($validated['position']) && empty($validated['position_id'])) {
						return response()->json([
							'message' => 'Position or position_id is required for assign_position action.',
						], 422);
					}
					$affected = Employee::query()->whereIn('id', $employeeIds)->update([
						'position' => $validated['position'] ?? null,
						'position_id' => $validated['position_id'] ?? null,
					]);
					break;

				case 'assign_manager':
					$lineManagerId = $validated['line_manager_id'] ?? null;
					if ($lineManagerId === null) {
						return response()->json([
							'message' => 'line_manager_id is required for assign_manager action.',
						], 422);
					}
					$affected = Employee::query()->whereIn('id', $employeeIds)->update([
						'line_manager_id' => $lineManagerId,
					]);
					break;
			}

			$this->auditLogger->log($request->user(), 'employee.bulk_action', null, [
				'domain' => 'employee',
				'action' => $action,
				'employee_ids' => $employeeIds,
				'affected' => $affected,
			], $request);

			return response()->json([
				'action' => $action,
				'employee_ids' => $employeeIds,
				'affected' => $affected,
			]);
		});
	}
}
