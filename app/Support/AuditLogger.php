<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditLogger
{
    public function log(?User $actor, string $action, ?Model $target = null, array $meta = [], ?Request $request = null): void
    {
        try {
            AuditLog::create([
                'actor_user_id' => $actor?->id,
                'actor_company_id' => $actor?->company_id,
                'active_company_id' => app(ActiveCompany::class)->id() ?? $actor?->company_id,
                'action' => $action,
                'target_type' => $target ? get_class($target) : null,
                'target_id' => $target?->getKey(),
                'ip' => $request?->ip(),
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
                'request_id' => $request?->headers->get('X-Request-ID'),
                'meta' => $meta,
            ]);
        } catch (\Throwable $e) {
            // Never break business flow due to audit logging failures.
        }
    }
}
