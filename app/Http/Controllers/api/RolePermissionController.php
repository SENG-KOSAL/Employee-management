<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\RolePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RolePermissionController extends Controller
{
    private array $allowedRoles = ['admin', 'hr', 'manager', 'employee'];

    public function show(Request $request, string $role)
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Forbidden');
        }

        if (! in_array($role, $this->allowedRoles, true)) {
            abort(422, 'Invalid role');
        }

        $keys = RolePermission::query()
            ->where('role', $role)
            ->with('permission:id,key')
            ->get()
            ->pluck('permission.key')
            ->values();

        return response()->json([
            'role' => $role,
            'permissions' => $keys,
        ]);
    }

    public function update(Request $request, string $role)
    {
        $user = $request->user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'Forbidden');
        }

        if (! in_array($role, $this->allowedRoles, true)) {
            abort(422, 'Invalid role');
        }

        $data = $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string',
        ]);

        $permissionKeys = array_values(array_unique($data['permissions']));

        $permissionIds = Permission::query()
            ->whereIn('key', $permissionKeys)
            ->pluck('id')
            ->all();

        if (count($permissionIds) !== count($permissionKeys)) {
            $existingKeys = Permission::query()->whereIn('key', $permissionKeys)->pluck('key')->all();
            $missing = array_values(array_diff($permissionKeys, $existingKeys));
            return response()->json([
                'message' => 'Some permissions do not exist',
                'missing' => $missing,
            ], 422);
        }

        DB::transaction(function () use ($role, $permissionIds) {
            RolePermission::where('role', $role)->delete();

            $rows = array_map(fn ($permissionId) => [
                'role' => $role,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ], $permissionIds);

            RolePermission::insert($rows);
        });

        return $this->show($request, $role);
    }
}
