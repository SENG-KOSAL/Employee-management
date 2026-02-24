<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\ActiveCompany;
use Illuminate\Http\Request;

class TenantContextController extends Controller
{
    public function show(Request $request, ActiveCompany $activeCompany)
    {
        $company = $activeCompany->company();

        return response()->json([
            'host' => $request->getHost(),
            'forwarded_host' => $request->header('X-Forwarded-Host'),
            'original_host' => $request->header('X-Original-Host'),
            'tenant_host' => $request->header('X-Tenant-Host'),
            'active_company_id' => $activeCompany->id(),
            'active_company' => $company ? [
                'id' => $company->id,
                'slug' => $company->slug,
                'name' => $company->name,
                'status' => $company->status,
            ] : null,
        ]);
    }
}
