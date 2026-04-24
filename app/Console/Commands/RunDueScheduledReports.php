<?php

namespace App\Console\Commands;

use App\Models\ScheduledReport;
use App\Models\User;
use App\Notifications\ReportReadyNotification;
use App\Support\ReportBuilderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RunDueScheduledReports extends Command
{
    protected $signature = 'reports:run-due';

    protected $description = 'Generate all due scheduled reports';

    public function handle(ReportBuilderService $reportBuilder): int
    {
        $dueReports = ScheduledReport::query()
            ->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->get();

        foreach ($dueReports as $report) {
            try {
                $dataset = $reportBuilder->buildDataset($report->filters ?? []);
                $result = $reportBuilder->export($dataset, $report->format, 'reports/scheduled');

                $report->update([
                    'last_run_at' => now(),
                    'last_generated_at' => now(),
                    'last_status' => 'success',
                    'last_error' => null,
                    'last_file_path' => $result['path'],
                    'next_run_at' => $this->nextRunAt($report->frequency),
                ]);

                if ($report->notify_on_ready && $report->creator) {
                    $report->creator->notify(new ReportReadyNotification($report->name, $report->format));
                }

                $recipientIds = collect($report->recipients ?? [])->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->values();
                if ($report->notify_on_ready && $recipientIds->isNotEmpty()) {
                    User::query()->whereIn('id', $recipientIds->all())->get()->each(function (User $recipient) use ($report) {
                        $recipient->notify(new ReportReadyNotification($report->name, $report->format));
                    });
                }

                $this->info("Generated report #{$report->id}");
            } catch (\Throwable $e) {
                $report->update([
                    'last_run_at' => now(),
                    'last_status' => 'failed',
                    'last_error' => $e->getMessage(),
                    'next_run_at' => $this->nextRunAt($report->frequency),
                ]);

                $this->error("Failed report #{$report->id}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    private function nextRunAt(string $frequency): Carbon
    {
        return match ($frequency) {
            'daily' => now()->addDay(),
            'weekly' => now()->addWeek(),
            default => now()->addMonth(),
        };
    }
}
