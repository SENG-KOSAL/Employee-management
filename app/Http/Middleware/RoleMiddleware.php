<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        if (! $user) {
            throw new AccessDeniedHttpException('Unauthenticated.');
        }

        if (! in_array($user->role, $roles, true)) {
            throw new AccessDeniedHttpException('Forbidden.');
        }

        return $next($request);
    }
}
