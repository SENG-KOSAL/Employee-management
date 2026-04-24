<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
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

    public function listPeriods(Request $request)
    {
        $this->authorizeAdminHr($request);

        return response()->json(
            PayrollPeriod::query()
                ->with(['creator', 'locker'])
                ->orderByDesc('start_date')
                ->paginate((int) $request->query('per_page', 20))
                ->withQueryString()
        );
    }

    public function createPeriod(Request $request)
    {
        $this->authorizeAdminHr($request);

        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'payment_date' => 'required|date|after_or_equal:end_date',
        ]);

        $this->ensureNoPeriodOverlap($data['start_date'], $data['end_date']);

        $period = PayrollPeriod::create([
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'payment_date' => $data['payment_date'],
            'is_locked' => false,
            'created_by' => $request->user()->id,
        ]);

        $this->auditLogger->log($request->user(), 'payroll.period_created', null, [
            'domain' => 'payroll',
            'payroll_period_id' => $period->id,
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
            'payment_date' => $period->payment_date?->toDateString(),
        ], $request);

        return response()->json($period->fresh(['creator', 'locker']), 201);
    }

    public function updatePeriod(Request $request, int $id)
    {
        $this->authorizeAdminHr($request);

        $period = PayrollPeriod::query()->findOrFail($id);

        $data = $request->validate([
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date',
            'payment_date' => 'sometimes|required|date',
        ]);

        $startDate = $data['start_date'] ?? $period->start_date?->toDateString();
        $endDate = $data['end_date'] ?? $period->end_date?->toDateString();
        $paymentDate = $data['payment_date'] ?? $period->payment_date?->toDateString();

        if ($endDate <= $startDate) {
            return response()->json([
                'message' => 'The end_date must be after start_date.',
            ], 422);
        }

        if ($paymentDate < $endDate) {
            return response()->json([
                'message' => 'The payment_date must be after or equal to end_date.',
            ], 422);
        }

        $this->ensureNoPeriodOverlap($startDate, $endDate, $period->id);

        $period->update([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'payment_date' => $paymentDate,
        ]);

        $this->auditLogger->log($request->user(), 'payroll.period_updated', null, [
            'domain' => 'payroll',
            'payroll_period_id' => $period->id,
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
            'payment_date' => $period->payment_date?->toDateString(),
        ], $request);

        return response()->json($period->fresh(['creator', 'locker']));
    }

    public function deletePeriod(Request $request, int $id)
    {
        $this->authorizeAdminHr($request);

        $period = PayrollPeriod::query()->findOrFail($id);
        $period->delete();

        $this->auditLogger->log($request->user(), 'payroll.period_deleted', null, [
            'domain' => 'payroll',
            'payroll_period_id' => $period->id,
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
        ], $request);

        return response()->json(['message' => 'Payroll period deleted']);
    }

    public function lockPayrollPeriod(Request $request, int $id)
    {
        $this->authorizeAdminHr($request);

        $period = PayrollPeriod::query()->findOrFail($id);

        $period->update([
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => $request->user()->id,
        ]);

        $this->auditLogger->log($request->user(), 'payroll.period_locked', null, [
            'domain' => 'payroll',
            'payroll_period_id' => $period->id,
            'start_date' => $period->start_date?->toDateString(),
            'end_date' => $period->end_date?->toDateString(),
        ], $request);

        return response()->json($period->fresh(['creator', 'locker']));
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

    private function ensureNoPeriodOverlap(string $startDate, string $endDate, ?int $ignoreId = null): void
    {
        $query = PayrollPeriod::query()
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            abort(422, 'Payroll period overlaps with an existing period');
        }
    }
}
