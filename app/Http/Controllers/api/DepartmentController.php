<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    /**
     * List all departments (Admin + HR only).
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        if (! $this->canView($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $departments = Department::query()->orderBy('name')->get();

        return response()->json([
            'data' => $departments,
        ], 200);
    }

    /**
     * Create a new department (Admin only).
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        if (! $this->isAdmin($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:departments,name'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $department = Department::create($validated);

        return response()->json([
            'message' => 'Department created successfully',
            'data' => $department,
        ], 201);
    }

    /**
     * View a single department (Admin + HR only).
     */
    public function show(Request $request, Department $department)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        if (! $this->canView($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json([
            'data' => $department,
        ], 200);
    }

    /**
     * Update department details (Admin only).
     */
    public function update(Request $request, Department $department)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        if (! $this->isAdmin($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('departments', 'name')->ignore($department->id),
            ],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $department->update($validated);

        return response()->json([
            'message' => 'Department updated successfully',
            'data' => $department,
        ], 200);
    }

    /**
     * Delete a department (Admin only).
     */
    public function destroy(Request $request, Department $department)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        if (! $this->isAdmin($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $department->delete();

        return response()->json([
            'message' => 'Department deleted successfully',
        ], 200);
    }

    private function isAdmin($user): bool
    {
        return $user && method_exists($user, 'isAdmin') && $user->isAdmin();
    }

    private function canView($user): bool
    {
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return true;
        }

        if (method_exists($user, 'isHr') && $user->isHr()) {
            return true;
        }

        return false;
    }
}
