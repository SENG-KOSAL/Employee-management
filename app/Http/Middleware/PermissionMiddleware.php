<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use Closure;
use Illuminate\Http\Request;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions)
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        // Admin bypass (optional, but usually desired)
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return $next($request);
        }

        $role = $user->role;
        if (! $role) {
            abort(403, 'Forbidden');
        }

        $has = RolePermission::query()
            ->where('role', $role)
            ->whereHas('permission', function ($q) use ($permissions) {
                $q->whereIn('key', $permissions);
            })
            ->exists();

        if (! $has) {
            abort(403, 'Forbidden');
        }

        return $next($request);
    }
}
