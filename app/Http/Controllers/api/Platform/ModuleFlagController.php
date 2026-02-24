<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ModuleFlag;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ModuleFlagController extends Controller
{
    public function index(Company $company)
    {
        return response()->json([
            'company_id' => $company->id,
            'modules' => $company->moduleFlags()->get(['module', 'enabled', 'enforced_at', 'meta']),
        ]);
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'modules' => 'required|array',
            // Accept both shapes:
            // - { name: "attendance", enabled: true }
            // - { module: "attendance", enabled: true } (current frontend)
            'modules.*.name' => 'nullable|string',
            'modules.*.module' => 'nullable|string',
            'modules.*.enabled' => 'required|boolean',
            'modules.*.meta' => 'nullable|array',
        ]);

        foreach ($data['modules'] as $idx => $module) {
            if (empty($module['name']) && empty($module['module'])) {
                throw ValidationException::withMessages([
                    "modules.{$idx}.name" => ['The modules.*.name field is required (or provide modules.*.module).'],
                ]);
            }
        }

        foreach ($data['modules'] as $module) {
            $moduleName = $module['name'] ?? $module['module'];
            ModuleFlag::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'module' => $moduleName,
                ],
                [
                    'enabled' => $module['enabled'],
                    'enforced_at' => now(),
                    'meta' => $module['meta'] ?? null,
                ]
            );
        }

        $company->modules_enabled = collect($data['modules'])
            ->mapWithKeys(fn ($mod) => [($mod['name'] ?? $mod['module']) => $mod['enabled']])
            ->toArray();
        $company->save();

        return response()->json([
            'message' => 'Modules updated',
            'modules_enabled' => $company->modules_enabled,
        ]);
    }
}
