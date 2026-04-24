<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Notifications\PayslipDeliveredNotification;
use App\Support\AuditLogger;
use App\Support\PayslipService;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    public function __construct(
        private readonly PayslipService $payslipService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function generate(Request $request, Payroll $payroll)
    {
        $this->authorizeAdminHr($request);

        $payslip = $this->payslipService->generate($payroll);

        $this->auditLogger->log($request->user(), 'payslip.generated', $payroll, [
            'domain' => 'payroll',
            'payslip_id' => $payslip->id,
        ], $request);

        return response()->json($payslip->fresh());
    }

    public function generateBatch(Request $request)
    {
        $this->authorizeAdminHr($request);

        $data = $request->validate([
            'payroll_ids' => 'required|array|min:1',
            'payroll_ids.*' => 'integer|exists:payrolls,id',
        ]);

        $generated = [];

        $payrolls = Payroll::query()->whereIn('id', $data['payroll_ids'])->get();
        foreach ($payrolls as $payroll) {
            $generated[] = $this->payslipService->generate($payroll);
        }

        $this->auditLogger->log($request->user(), 'payslip.batch_generated', null, [
            'domain' => 'payroll',
            'count' => count($generated),
            'payroll_ids' => $data['payroll_ids'],
        ], $request);

        return response()->json([
            'count' => count($generated),
            'payslips' => $generated,
        ]);
    }

    public function distribute(Request $request, Payroll $payroll)
    {
        $this->authorizeAdminHr($request);

        $data = $request->validate([
            'channel' => 'required|in:email,portal',
            'email' => 'nullable|email',
        ]);

        $payslip = $payroll->payslip ?: $this->payslipService->generate($payroll);

        try {
            if ($data['channel'] === 'email') {
                $recipient = $data['email'] ?? $payroll->employee?->email;

                if (! $recipient) {
                    abort(422, 'Employee email is required for email delivery');
                }

                $employeeUser = $payroll->employee?->user;
                if ($employeeUser) {
                    $employeeUser->notify(new PayslipDeliveredNotification(
                        $payroll->id,
                        optional($payroll->period_start)->format('Y-m') ?? 'N/A'
                    ));
                }

                $payslip->update([
                    'delivery_status' => 'delivered',
                    'delivery_channel' => 'email',
                    'delivered_at' => now(),
                    'emailed_to' => $recipient,
                    'last_error' => null,
                ]);
            } else {
                $employeeUser = $payroll->employee?->user;
                if ($employeeUser) {
                    $employeeUser->notify(new PayslipDeliveredNotification(
                        $payroll->id,
                        optional($payroll->period_start)->format('Y-m') ?? 'N/A'
                    ));
                }

                $payslip->update([
                    'delivery_status' => 'available_in_portal',
                    'delivery_channel' => 'portal',
                    'delivered_at' => now(),
                    'last_error' => null,
                ]);
            }
        } catch (\Throwable $e) {
            $payslip->update([
                'delivery_status' => 'failed',
                'delivery_channel' => $data['channel'],
                'last_error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->auditLogger->log($request->user(), 'payslip.distributed', $payroll, [
            'domain' => 'payroll',
            'channel' => $data['channel'],
            'status' => $payslip->delivery_status,
        ], $request);

        return response()->json($payslip->fresh());
    }

    public function download(Request $request, Payroll $payroll)
    {
        $user = $request->user();
        $isSelf = $user?->isEmployee() && $user->employee_id === $payroll->employee_id;

        if (! $isSelf && ! ($user?->isAdmin() || $user?->isHr())) {
            abort(403, 'Forbidden');
        }

        $payslip = $payroll->payslip;
        if (! $payslip || ! $payslip->file_path) {
            abort(404, 'Payslip file not found');
        }

        $absolutePath = storage_path('app/private/' . $payslip->file_path);
        if (! file_exists($absolutePath)) {
            abort(404, 'Payslip file missing in storage');
        }

        return response()->download($absolutePath, basename($absolutePath), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function authorizeAdminHr(Request $request): void
    {
        $user = $request->user();
        if (! ($user?->isAdmin() || $user?->isHr())) {
            abort(403, 'Forbidden');
        }
    }
}
