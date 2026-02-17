<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Support\ActiveCompany;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AuditTrail
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $user = $request->user();
        $activeCompany = app(ActiveCompany::class);

        if ($user) {
            try {
                AuditLog::create([
                    'actor_user_id' => $user->id,
                    'actor_company_id' => $user->company_id,
                    'active_company_id' => $activeCompany->id(),
                    'action' => sprintf('%s %s', strtoupper($request->method()), $request->path()),
                    'target_type' => null,
                    'target_id' => null,
                    'ip' => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 255),
                    'request_id' => $request->headers->get('X-Request-ID', Str::uuid()->toString()),
                    'meta' => [
                        'status' => $response->getStatusCode(),
                        'route' => optional($request->route())->getName(),
                    ],
                ]);
            } catch (\Throwable $e) {
                // Avoid breaking the response flow if auditing fails.
            }
        }

        return $response;
    }
}
