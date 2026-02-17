<?php

namespace App\Http\Middleware;

use App\Support\ActiveCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnforceActiveCompany
{
    public function handle(Request $request, Closure $next)
    {
        $activeCompany = app(ActiveCompany::class);

        if ($activeCompany->id() === null) {
            throw new AccessDeniedHttpException('Active company context is required.');
        }

        return $next($request);
    }
}
