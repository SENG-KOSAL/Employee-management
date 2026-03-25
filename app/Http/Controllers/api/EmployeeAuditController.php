<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeAuditController extends Controller
{
    public function index(Request $request, Employee $employee)
    {
        $user = $request->user();
        $isSelf = $user?->isEmployee() && $user->employee_id === $employee->id;

        if (! $isSelf && ! ($user?->isAdmin() || $user?->isHr() || $user?->isManager())) {
            abort(403, 'Forbidden');
        }

        $perPage = (int) $request->query('per_page', 20);

        $logs = AuditLog::query()
            ->where('target_type', Employee::class)
            ->where('target_id', $employee->id)
            ->with('actor')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($logs);
    }
}
