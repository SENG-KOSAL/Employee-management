<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Support\ActiveCompany;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ResolveActiveCompany
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            throw new AccessDeniedHttpException('Unauthenticated.');
        }

        $activeCompany = app(ActiveCompany::class);

        // Optional host-based tenant resolution.
        // Example: a.local -> company slug "a".
        // When running behind a dev proxy (e.g. Next.js / Vite), the backend may see Host=localhost.
        // Prefer forwarded/original host headers if present.
        $forwardedHost = $request->header('X-Forwarded-Host')
            ?: $request->header('X-Original-Host')
            ?: $request->header('X-Tenant-Host');

        $host = (string) ($forwardedHost ?: $request->getHost());
        // Some proxies send multiple hosts in X-Forwarded-Host: "a.local, proxy.local".
        $host = trim(explode(',', $host)[0]);
        // Remove port if present.
        $host = preg_replace('/:\\d+$/', '', $host) ?? $host;

        $hostSlug = strtolower((string) strtok($host, '.'));
        $hostCompany = null;
        if ($hostSlug !== '' && ! in_array($hostSlug, ['localhost', '127', '0'], true)) {
            $hostCompany = Company::query()->active()->where('slug', $hostSlug)->first();
        }

        if ($user->isSuperAdmin()) {
            // Stateless API: super admin can set context using header.
            // If header is not provided, try to infer from host (slug).
            $activeCompanyId = $request->header('X-Active-Company')
                ?: $request->query('active_company_id');

            if ($activeCompanyId) {
                $company = Company::query()->active()->find($activeCompanyId);
                if (! $company) {
                    throw new AccessDeniedHttpException('Company not available or suspended.');
                }

                $activeCompany->set($company);
            } elseif ($hostCompany) {
                $activeCompany->set($hostCompany);
            } else {
                $activeCompany->clear();
            }
        } else {
            if (! $user->company_id) {
                throw new BadRequestHttpException('User is not assigned to a company.');
            }

            if ($hostCompany) {
                if ((int) $user->company_id !== (int) $hostCompany->id) {
                    throw new AccessDeniedHttpException('Forbidden: company does not match this domain.');
                }

                $activeCompany->set($hostCompany);
            } else {
                $company = Company::query()->active()->find($user->company_id);
                if (! $company) {
                    throw new AccessDeniedHttpException('Company not available or suspended.');
                }

                $activeCompany->set($company);
            }
        }

        $request->attributes->set('active_company_id', $activeCompany->id());

        return $next($request);
    }
}
