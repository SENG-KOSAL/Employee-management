<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr() || $user->isManager())) {
            abort(403, 'Forbidden');
        }

        $query = AuditLog::query()->with('actor')->orderByDesc('created_at');

        if ($domain = $request->query('domain')) {
            $query->where('meta->domain', $domain);
        }

        if ($action = $request->query('action')) {
            $query->where('action', 'like', '%' . $action . '%');
        }

        if ($targetType = $request->query('target_type')) {
            $query->where('target_type', $targetType);
        }

        if ($targetId = $request->query('target_id')) {
            $query->where('target_id', $targetId);
        }

        if ($from = $request->query('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        $perPage = (int) $request->query('per_page', 20);

        return response()->json($query->paginate($perPage)->withQueryString());
    }
}
