<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Support\ReportBuilderService;
use Illuminate\Http\Request;

class ReportingController extends Controller
{
    public function __construct(private readonly ReportBuilderService $reportBuilder)
    {
    }

    public function summary(Request $request)
    {
        $this->authorizeReportingAccess($request);

        $dataset = $this->reportBuilder->buildDataset($this->validatedFilters($request));

        return response()->json($dataset['summary']);
    }

    public function drilldownEmployees(Request $request)
    {
        $this->authorizeReportingAccess($request);

        $filters = $this->validatedFilters($request);
        $query = Employee::query()->with('user');

        if (! empty($filters['department'])) {
            $query->where('department', $filters['department']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['manager'])) {
            $query->where('line_manager_id', $filters['manager']);
        }
        if (! empty($filters['role'])) {
            $query->whereHas('user', fn ($q) => $q->where('role', $filters['role']));
        }

        return response()->json($query->paginate((int) $request->query('per_page', 20))->withQueryString());
    }

    public function drilldownLeaves(Request $request)
    {
        $this->authorizeReportingAccess($request);

        $filters = $this->validatedFilters($request);
        $query = LeaveRequest::query()->with(['employee', 'leaveType']);

        if (! empty($filters['period_start']) && ! empty($filters['period_end'])) {
            $query->whereDate('start_date', '>=', $filters['period_start'])
                ->whereDate('end_date', '<=', $filters['period_end']);
        }
        if (! empty($filters['department'])) {
            $query->whereHas('employee', fn ($q) => $q->where('department', $filters['department']));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['manager'])) {
            $query->whereHas('employee', fn ($q) => $q->where('line_manager_id', $filters['manager']));
        }

        return response()->json($query->paginate((int) $request->query('per_page', 20))->withQueryString());
    }

    public function drilldownPayrolls(Request $request)
    {
        $this->authorizeReportingAccess($request);

        $filters = $this->validatedFilters($request);
        $query = Payroll::query()->with('employee');

        if (! empty($filters['period_start']) && ! empty($filters['period_end'])) {
            $query->whereDate('period_start', '>=', $filters['period_start'])
                ->whereDate('period_end', '<=', $filters['period_end']);
        }
        if (! empty($filters['department'])) {
            $query->whereHas('employee', fn ($q) => $q->where('department', $filters['department']));
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['manager'])) {
            $query->whereHas('employee', fn ($q) => $q->where('line_manager_id', $filters['manager']));
        }

        return response()->json($query->paginate((int) $request->query('per_page', 20))->withQueryString());
    }

    public function export(Request $request)
    {
        $this->authorizeReportingAccess($request);

        $data = $request->validate([
            'format' => 'required|in:csv,excel,pdf',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'department' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'manager' => 'nullable|integer|exists:employees,id',
        ]);

        $dataset = $this->reportBuilder->buildDataset($data);
        $result = $this->reportBuilder->export($dataset, $data['format']);

        return response()->download(storage_path('app/private/' . $result['path']), $result['filename'], [
            'Content-Type' => $result['mime'],
        ]);
    }

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'department' => 'nullable|string|max:255',
            'role' => 'nullable|string|max:50',
            'status' => 'nullable|string|max:50',
            'manager' => 'nullable|integer|exists:employees,id',
        ]);
    }

    private function authorizeReportingAccess(Request $request): void
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr() || $user->isManager())) {
            abort(403, 'Forbidden');
        }
    }
}
