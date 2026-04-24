<?php

namespace App\Http\Controllers\Api;

use App\Exports\EmployeesExport;
use App\Exports\EmployeesTemplateExport;
use App\Http\Controllers\Controller;
use App\Imports\EmployeeDynamicImport;
use App\Support\ActiveCompany;
use App\Support\EmployeeImportSchema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeExcelController extends Controller
{
    public function export(Request $request, ActiveCompany $activeCompany): BinaryFileResponse|JsonResponse
    {
        $companyId = $activeCompany->id() ?? $request->user()?->company_id;

        if (! $companyId) {
            return response()->json([
                'message' => 'Active company is required for employee export.',
            ], 422);
        }

        $filters = $request->only(['department', 'position', 'status']);
        $filename = 'employees-' . $companyId . '-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new EmployeesExport((int) $companyId, $filters), $filename);
    }

    public function template(EmployeeImportSchema $schemaReader): BinaryFileResponse
    {
        $columns = $schemaReader->importableColumns();
        $filename = 'employees-template-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new EmployeesTemplateExport($columns), $filename);
    }

    public function import(Request $request, EmployeeImportSchema $schemaReader, ActiveCompany $activeCompany): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ]);

        $companyId = $activeCompany->id() ?? $request->user()?->company_id;

        if (! $companyId) {
            return response()->json([
                'message' => 'Active company is required for employee import.',
            ], 422);
        }

        $import = new EmployeeDynamicImport((int) $companyId, $schemaReader);
        Excel::import($import, $validated['file']);

        return response()->json($import->results());
    }
}
