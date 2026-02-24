<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Forbidden');
        }

        return Permission::query()->orderBy('key')->get();
    }

    public function store(Request $request)
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Forbidden');
        }

        $data = $request->validate([
            'key' => 'required|string|max:190',
            'description' => 'nullable|string|max:255',
        ]);

        $permission = Permission::updateOrCreate(
            ['key' => $data['key']],
            ['description' => $data['description'] ?? null]
        );

        return response()->json($permission, 201);
    }
}
