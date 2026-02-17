<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\LoginAsCompanyEvent;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ContextController extends Controller
{
    public function enter(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->isSuperAdmin()) {
            throw new AccessDeniedHttpException('Only super admin can switch context.');
        }

        $data = $request->validate([
            'company_id' => 'required|exists:companies,id',
            'reason' => 'nullable|string|max:500',
        ]);

        $company = Company::query()->active()->findOrFail($data['company_id']);

        $event = LoginAsCompanyEvent::create([
            'super_admin_id' => $user->id,
            'company_id' => $company->id,
            'reason' => $data['reason'] ?? null,
            'audit_trail' => ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
        ]);

        return response()->json([
            'message' => 'Context set',
            'active_company_id' => $company->id,
            'active_company_name' => $company->name,
            'support_mode' => true,
            'read_only' => true,
            'hint' => 'Send X-Active-Company header with this company_id on subsequent tenant requests.',
            'login_as_event_id' => $event->id,
        ]);
    }

    public function exit(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->isSuperAdmin()) {
            throw new AccessDeniedHttpException('Only super admin can exit context.');
        }

        $event = LoginAsCompanyEvent::query()
            ->where('super_admin_id', $user->id)
            ->whereNull('exited_at')
            ->latest('entered_at')
            ->first();

        if ($event) {
            $event->update(['exited_at' => now()]);
        }

        return response()->json([
            'message' => 'Context cleared',
            'support_mode' => false,
        ]);
    }
}
