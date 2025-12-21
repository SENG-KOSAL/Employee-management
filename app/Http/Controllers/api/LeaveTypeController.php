<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveTypeRequest;
use App\Http\Requests\UpdateLeaveTypeRequest;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveType;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function __construct()
    {
        // Ensure routes using this controller are protected by auth middleware in routes/api.php
        // e.g. ->middleware('auth:sanctum')
    }

    /**
     * List all leave types (paginated)
     * Accessible by all authenticated users (employees can view)
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $query = LeaveType::query()->orderBy('name');

        // optional search by name
        if ($search = $request->query('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $items = $query->paginate($perPage)->withQueryString();

        return LeaveTypeResource::collection($items);
    }

    /**
     * Store a new leave type
     * Only Admin or HR
     */
    public function store(StoreLeaveTypeRequest $request)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validated();
        $leaveType = LeaveType::create($data);

        return new LeaveTypeResource($leaveType);
    }

    /**
     * Show a single leave type
     */
    public function show(LeaveType $leaveType)
    {
        return new LeaveTypeResource($leaveType);
    }

    /**
     * Update a leave type
     * Only Admin or HR
     */
    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $leaveType->update($request->validated());

        return new LeaveTypeResource($leaveType);
    }

    /**
     * Delete a leave type
     * Only Admin or HR (adjust if you want Admin-only)
     */
    public function destroy(Request $request, LeaveType $leaveType)
    {
        $user = $request->user();
        if (! ($user->isAdmin() || $user->isHr())) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $leaveType->delete();

        return response()->noContent();
    }
}