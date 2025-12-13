<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\Employee\EmployeeResource;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    // Optionally use middleware('auth:sanctum') if protected
    public function __construct()
    {
        // $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $query = Employee::query();

        // Search (name, email, code)
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'ilike', "%{$search}%")
                  ->orWhere('last_name', 'ilike', "%{$search}%")
                  ->orWhere('email', 'ilike', "%{$search}%")
                  ->orWhere('employee_code', 'ilike', "%{$search}%");
            });
        }

        // Filters
        if ($department = $request->query('department')) {
            $query->where('department', $department);
        }
        if ($position = $request->query('position')) {
            $query->where('position', $position);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'created_at');
        $sortDir = $request->query('sort_dir', 'desc');
        $allowedSorts = ['created_at', 'first_name', 'start_date', 'salary', 'employee_code'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }
        $query->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc');

        $perPage = (int) $request->query('per_page', 15);
        $employees = $query->paginate($perPage)->withQueryString();

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $this->storePhoto($request->file('photo'), $data['employee_code'] ?? Str::random(8));
        }

        // Use provided password from request (validated)
        $providedPassword = $data['password'];

        return DB::transaction(function () use ($data, $providedPassword) {
            // Create employee first
            $employee = Employee::create($data);

            // Auto-create user account for this employee
            $user = User::create([
                'name' => $employee->full_name,
                'email' => $employee->email,
                'password' => $providedPassword,
                'role' => 'employee',
                'employee_id' => $employee->id,
            ]);

            // Return employee resource with generated credentials
            $resource = new EmployeeResource($employee);
            $response = $resource->toArray(request());
            $response['login_credentials'] = [
                'email' => $user->email,
                'message' => 'User account created automatically using provided password.',
            ];

            return response()->json($response, 201);
        });
    }

    public function show(Employee $employee)
    {
        return new EmployeeResource($employee);
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $data = $request->validated();

        if ($request->hasFile('photo')) {
            // delete old photo if exists
            if ($employee->photo_path) {
                Storage::disk('public')->delete($employee->photo_path);
            }
            $data['photo_path'] = $this->storePhoto($request->file('photo'), $data['employee_code'] ?? $employee->employee_code);
        }

        $employee->update($data);

        return new EmployeeResource($employee);
    }

    public function destroy(Employee $employee)
    {
        if ($employee->photo_path) {
            Storage::disk('public')->delete($employee->photo_path);
        }
        $employee->delete();

        return response()->noContent();
    }

    protected function storePhoto($file, string $identifier): string
    {
        // store under employees/{year}/{identifier}-{timestamp}.{ext}
        $filename = sprintf('%s-%s.%s', $identifier, time(), $file->getClientOriginalExtension());
        $path = $file->storeAs('employees/' . date('Y'), $filename, ['disk' => 'public']);
        // optionally resize using Intervention Image before storing
        return $path;
    }
}