<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SuperAdminReadOnly
{
    /**
     * Super admins are allowed full access in any active company context.
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }
}
