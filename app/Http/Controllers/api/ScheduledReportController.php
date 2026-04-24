<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScheduledReport;
use App\Models\User;
use App\Notifications\ReportReadyNotification;
use App\Support\ReportBuilderService;
use Illuminate\Http\Request;

class ScheduledReportController extends Controller
{
    public function __construct(private readonly ReportBuilderService $reportBuilder)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeAdminHr($request);

        return response()->json(
            ScheduledReport::query()->orderByDesc('created_at')->paginate((int) $request->query('per_page', 20))->withQueryString()
        );
    }

    public function store(Request $request)
    {
        $this->authorizeAdminHr($request);

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'report_type' => 'nullable|string|max:50',
            'format' => 'required|in:csv,excel,pdf',
            'frequency' => 'required|in:daily,weekly,monthly',
            'filters' => 'nullable|array',
            'recipients' => 'nullable|array',
            'notify_on_ready' => 'nullable|boolean',
            'next_run_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
        ]);

        $report = ScheduledReport::create([
            'created_by' => $request->user()->id,
            ...$data,
            'next_run_at' => $data['next_run_at'] ?? now()->addMinute(),
            'is_active' => $data['is_active'] ?? true,
            'notify_on_ready' => $data['notify_on_ready'] ?? false,
        ]);

        return response()->json($report, 201);
    }

    public function show(Request $request, ScheduledReport $scheduledReport)
    {
        $this->authorizeAdminHr($request);

        return response()->json($scheduledReport);
    }

    public function update(Request $request, ScheduledReport $scheduledReport)
    {
        $this->authorizeAdminHr($request);

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'report_type' => 'sometimes|string|max:50',
            'format' => 'sometimes|in:csv,excel,pdf',
            'frequency' => 'sometimes|in:daily,weekly,monthly',
            'filters' => 'sometimes|array',
            'recipients' => 'sometimes|array',
            'notify_on_ready' => 'sometimes|boolean',
            'next_run_at' => 'sometimes|date',
            'is_active' => 'sometimes|boolean',
        ]);

        $scheduledReport->update($data);

        return response()->json($scheduledReport->fresh());
    }

    public function destroy(Request $request, ScheduledReport $scheduledReport)
    {
        $this->authorizeAdminHr($request);

        $scheduledReport->delete();

        return response()->noContent();
    }

    public function runNow(Request $request, ScheduledReport $scheduledReport)
    {
        $this->authorizeAdminHr($request);

        $dataset = $this->reportBuilder->buildDataset($scheduledReport->filters ?? []);
        $result = $this->reportBuilder->export($dataset, $scheduledReport->format, 'reports/scheduled');

        $scheduledReport->update([
            'last_run_at' => now(),
            'last_generated_at' => now(),
            'last_status' => 'success',
            'last_error' => null,
            'last_file_path' => $result['path'],
            'next_run_at' => now()->addMinute(),
        ]);

        if ($scheduledReport->notify_on_ready && $scheduledReport->creator) {
            $scheduledReport->creator->notify(new ReportReadyNotification($scheduledReport->name, $scheduledReport->format));
        }

        $recipientIds = collect($scheduledReport->recipients ?? [])->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->values();
        if ($scheduledReport->notify_on_ready && $recipientIds->isNotEmpty()) {
            User::query()->whereIn('id', $recipientIds->all())->get()->each(function (User $recipient) use ($scheduledReport) {
                $recipient->notify(new ReportReadyNotification($scheduledReport->name, $scheduledReport->format));
            });
        }

        return response()->json([
            'message' => 'Report generated successfully',
            'file' => $result,
            'report' => $scheduledReport->fresh(),
        ]);
    }

    private function authorizeAdminHr(Request $request): void
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr())) {
            abort(403, 'Forbidden');
        }
    }
}
