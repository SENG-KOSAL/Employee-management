<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriodLock;
use App\Support\AuditLogger;
use App\Support\PayrollGovernanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollGovernanceController extends Controller
{
    public function __construct(
        private readonly PayrollGovernanceService $governance,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function lockPeriod(Request $request)
    {
        $this->authorizeAdminHr($request);

        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
            'reason' => 'nullable|string',
        ]);

        $lock = PayrollPeriodLock::updateOrCreate([
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
        ], [
            'locked_by' => $request->user()->id,
            'locked_at' => now(),
            'reason' => $data['reason'] ?? null,
        ]);

        $this->auditLogger->log($request->user(), 'payroll.period_locked', null, [
            'domain' => 'payroll',
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
        ], $request);

        return response()->json($lock);
    }

    public function unlockPeriod(Request $request)
    {
        $this->authorizeAdminHr($request);

        $data = $request->validate([
            'period_start' => 'required|date',
            'period_end' => 'required|date|after_or_equal:period_start',
        ]);

        PayrollPeriodLock::query()
            ->whereDate('period_start', $data['period_start'])
            ->whereDate('period_end', $data['period_end'])
            ->delete();

        $this->auditLogger->log($request->user(), 'payroll.period_unlocked', null, [
            'domain' => 'payroll',
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
        ], $request);

        return response()->json(['message' => 'Period unlocked']);
    }

    public function listLocks(Request $request)
    {
        $this->authorizeAdminHr($request);

        return response()->json(
            PayrollPeriodLock::query()->with('locker')->orderByDesc('period_start')->paginate((int) $request->query('per_page', 20))->withQueryString()
        );
    }

    public function approveAdjustment(Request $request, Payroll $payroll, PayrollAdjustment $adjustment)
    {
        $this->authorizeAdminHr($request);

        if ($adjustment->payroll_id !== $payroll->id) {
            abort(404, 'Adjustment does not belong to payroll');
        }

        if ($adjustment->status !== 'pending') {
            abort(422, 'Only pending adjustments can be approved');
        }

        if ((int) $adjustment->requested_by === (int) $request->user()->id) {
            abort(422, 'Maker-checker policy violation: requester cannot approve own adjustment');
        }

        if ($this->governance->isLocked($payroll)) {
            abort(422, 'Payroll period is locked');
        }

        $data = $request->validate([
            'decision_notes' => 'nullable|string|max:2000',
            'override_exceptions' => 'nullable|boolean',
        ]);

        $flags = $this->governance->evaluateExceptionFlags($payroll, $adjustment);
        $allowOverride = (bool) ($data['override_exceptions'] ?? false);

        if (! empty($flags) && ! $allowOverride) {
            return response()->json([
                'message' => 'Exception rules violated',
                'exception_flags' => $flags,
            ], 422);
        }

        DB::transaction(function () use ($payroll, $adjustment, $request, $data, $flags) {
            $amount = (float) $adjustment->amount;

            if ($adjustment->kind === 'earning') {
                $payroll->benefits_total = (float) $payroll->benefits_total + $amount;
            } else {
                $payroll->deductions_total = (float) $payroll->deductions_total + $amount;
            }

            $grossPay = (float) $payroll->base_pay + (float) $payroll->overtime_pay + (float) $payroll->benefits_total;
            $netPay = $grossPay - (float) $payroll->deductions_total - (float) $payroll->unpaid_leave_deduction;

            $payroll->gross_pay = $grossPay;
            $payroll->net_pay = $netPay;
            $payroll->save();

            $adjustment->update([
                'status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'decision_notes' => $data['decision_notes'] ?? null,
                'applied_at' => now(),
                'exception_flags' => $flags,
            ]);

            $payroll->auditLogs()->create([
                'user_id' => $request->user()->id,
                'action' => 'adjustment_approved',
                'changes' => [
                    'adjustment_id' => $adjustment->id,
                    'flags' => $flags,
                    'decision_notes' => $data['decision_notes'] ?? null,
                ],
            ]);
        });

        $this->auditLogger->log($request->user(), 'payroll.adjustment_approved', $payroll, [
            'domain' => 'payroll',
            'adjustment_id' => $adjustment->id,
            'exception_flags' => $flags,
        ], $request);

        return response()->json([
            'payroll' => $payroll->fresh(['employee', 'adjustments']),
            'adjustment' => $adjustment->fresh(),
        ]);
    }

    public function rejectAdjustment(Request $request, Payroll $payroll, PayrollAdjustment $adjustment)
    {
        $this->authorizeAdminHr($request);

        if ($adjustment->payroll_id !== $payroll->id) {
            abort(404, 'Adjustment does not belong to payroll');
        }

        if ($adjustment->status !== 'pending') {
            abort(422, 'Only pending adjustments can be rejected');
        }

        if ((int) $adjustment->requested_by === (int) $request->user()->id) {
            abort(422, 'Maker-checker policy violation: requester cannot reject own adjustment');
        }

        $data = $request->validate([
            'decision_notes' => 'nullable|string|max:2000',
        ]);

        $adjustment->update([
            'status' => 'rejected',
            'rejected_by' => $request->user()->id,
            'rejected_at' => now(),
            'decision_notes' => $data['decision_notes'] ?? null,
        ]);

        $payroll->auditLogs()->create([
            'user_id' => $request->user()->id,
            'action' => 'adjustment_rejected',
            'changes' => [
                'adjustment_id' => $adjustment->id,
                'decision_notes' => $data['decision_notes'] ?? null,
            ],
        ]);

        $this->auditLogger->log($request->user(), 'payroll.adjustment_rejected', $payroll, [
            'domain' => 'payroll',
            'adjustment_id' => $adjustment->id,
        ], $request);

        return response()->json($adjustment->fresh());
    }

    private function authorizeAdminHr(Request $request): void
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr())) {
            abort(403, 'Forbidden');
        }
    }
}
