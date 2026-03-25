<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employee\StoreEmployeeRequest;
use App\Http\Requests\Employee\UpdateEmployeeRequest;
use App\Http\Resources\Employee\EmployeeResource;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger)
    {
    }

    // Optionally use middleware('auth:sanctum') if protected
    // public function __construct() { }

    public function index(Request $request)
    {
        $user = $request->user();
        if ($user && $user->isEmployee()) {
            abort(403, 'Employees cannot list other employees');
        }

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
        $employees = $query->with('document')->paginate($perPage)->withQueryString();

        return EmployeeResource::collection($employees);
    }

    public function store(StoreEmployeeRequest $request)
    {
        $data = $request->validated();

        $fullName = trim((string) ($data['name'] ?? ''));
        if (! isset($data['first_name']) || $data['first_name'] === null || $data['first_name'] === '') {
            [$firstName, $lastName] = $this->splitName($fullName);
            $data['first_name'] = $firstName;
            if (! isset($data['last_name']) || $data['last_name'] === null || $data['last_name'] === '') {
                $data['last_name'] = $lastName;
            }
        }

        if (! isset($data['phone']) || $data['phone'] === null || $data['phone'] === '') {
            $data['phone'] = $data['phone_number'] ?? null;
        }

        if (! isset($data['start_date']) || $data['start_date'] === null || $data['start_date'] === '') {
            $data['start_date'] = $data['join_date'] ?? null;
        }

        if (empty($data['employee_code'])) {
            $data['employee_code'] = $this->generateEmployeeCode();
        }

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $this->storePhoto($request->file('photo'), $data['employee_code'] ?? Str::random(8));
        }

        // Use provided password from request, otherwise generate a temporary one.
        $providedPassword = $data['password'] ?? Str::password(12);
        $isGeneratedPassword = ! isset($data['password']) || empty($data['password']);
        $userName = $data['name'] ?? null;
        $userRole = $data['role'] ?? 'employee';

        unset($data['name'], $data['role'], $data['password'], $data['phone_number'], $data['join_date']);

        return DB::transaction(function () use ($data, $providedPassword, $userName, $userRole, $isGeneratedPassword, $request) {
            // Create employee first
            $employee = Employee::create($data);

            // Auto-create user account for this employee
            $user = User::create([
                'name' => $userName ?: $employee->full_name,
                'email' => $employee->email,
                'password' => $providedPassword,
                'role' => $userRole,
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
            ]);

            // Return employee resource with generated credentials
            $resource = new EmployeeResource($employee->load(['user', 'document']));
            $response = $resource->toArray(request());
            $response['login_credentials'] = [
                'email' => $user->email,
                'message' => $isGeneratedPassword
                    ? 'User account created automatically using generated temporary password.'
                    : 'User account created automatically using provided password.',
            ];

            if ($isGeneratedPassword) {
                $response['login_credentials']['temporary_password'] = $providedPassword;
            }

            $this->auditLogger->log($request->user(), 'employee.created', $employee, [
                'domain' => 'employee',
                'fields' => array_keys($employee->getAttributes()),
            ], $request);

            return response()->json($response, 201);
        });
    }

    protected function splitName(string $fullName): array
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return ['N/A', null];
        }

        $parts = preg_split('/\s+/', $fullName) ?: [];
        $first = array_shift($parts) ?: 'N/A';
        $last = ! empty($parts) ? implode(' ', $parts) : null;

        return [$first, $last];
    }

    protected function generateEmployeeCode(): string
    {
        do {
            $code = 'EMP-' . strtoupper(Str::random(8));
        } while (Employee::query()->where('employee_code', $code)->exists());

        return $code;
    }

    public function show(Employee $employee)
    {
        $user = request()->user();
        if ($user && $user->isEmployee() && $user->employee_id !== $employee->id) {
            abort(403, 'Forbidden');
        }
        return new EmployeeResource($employee->load(['user', 'document']));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee)
    {
        $user = $request->user();
        if ($user && $user->isEmployee() && $user->employee_id !== $employee->id) {
            abort(403, 'Forbidden');
        }
        $data = $request->validated();

        $userData = [];
        if (array_key_exists('name', $data)) {
            $userData['name'] = $data['name'];
            unset($data['name']);
        }
        if (array_key_exists('role', $data)) {
            $userData['role'] = $data['role'];
            unset($data['role']);
        }
        if (! empty($data['password'])) {
            $userData['password'] = $data['password'];
            unset($data['password']);
        }

        if ($request->hasFile('photo')) {
            // delete old photo if exists
            if ($employee->photo_path) {
                Storage::disk('public')->delete($employee->photo_path);
            }
            $data['photo_path'] = $this->storePhoto($request->file('photo'), $data['employee_code'] ?? $employee->employee_code);
        }

        return DB::transaction(function () use ($employee, $data, $userData, $request) {
            $before = $employee->getOriginal();
            $employee->update($data);

            // Keep linked user in sync (if exists)
            $user = $employee->user;
            if ($user) {
                // If employee email changed and user email was tied to it, sync
                if (array_key_exists('email', $data)) {
                    $userData['email'] = $employee->email;
                }
                if (array_key_exists('password', $userData)) {
                    $userData['password'] = Hash::make($userData['password']);
                }
                // Don't overwrite name with null
                if (array_key_exists('name', $userData) && $userData['name'] === null) {
                    unset($userData['name']);
                }
                // Don't overwrite role with null
                if (array_key_exists('role', $userData) && $userData['role'] === null) {
                    unset($userData['role']);
                }
                if (! empty($userData)) {
                    $user->update($userData);
                }
            }

            $this->auditLogger->log($request->user(), 'employee.updated', $employee, [
                'domain' => 'employee',
                'before' => $before,
                'after' => $employee->fresh()->getAttributes(),
            ], $request);

            return new EmployeeResource($employee->load(['user', 'document']));
        });
    }

    public function uploadDocuments(Request $request, Employee $employee)
    {
        $user = $request->user();
        if ($user && $user->isEmployee() && $user->employee_id !== $employee->id) {
            abort(403, 'Forbidden');
        }

        $validated = $request->validate([
            // multipart/form-data field names expected by frontend
            'id_card' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'contract' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'cv' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'certificate' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        $doc = $employee->document()->first();

        $updates = [];

        if ($request->hasFile('id_card')) {
            if ($doc?->id_card_file_path) {
                Storage::disk('public')->delete($doc->id_card_file_path);
            }
            $updates['id_card_file_path'] = $this->storeEmployeeDocumentFile($employee, $request->file('id_card'), 'id-card');
        }

        if ($request->hasFile('contract')) {
            if ($doc?->contract_file_path) {
                Storage::disk('public')->delete($doc->contract_file_path);
            }
            $updates['contract_file_path'] = $this->storeEmployeeDocumentFile($employee, $request->file('contract'), 'contract');
        }

        if ($request->hasFile('cv')) {
            if ($doc?->cv_file_path) {
                Storage::disk('public')->delete($doc->cv_file_path);
            }
            $updates['cv_file_path'] = $this->storeEmployeeDocumentFile($employee, $request->file('cv'), 'cv');
        }

        if ($request->hasFile('certificate')) {
            if ($doc?->certificate_file_path) {
                Storage::disk('public')->delete($doc->certificate_file_path);
            }
            $updates['certificate_file_path'] = $this->storeEmployeeDocumentFile($employee, $request->file('certificate'), 'certificate');
        }

        if (empty($updates)) {
            abort(422, 'No documents were provided. Use multipart fields: id_card, contract, cv, certificate');
        }

        EmployeeDocument::query()->updateOrCreate(
            ['employee_id' => $employee->id],
            $updates
        );

        return new EmployeeResource($employee->fresh()->load(['user', 'document']));
    }

    public function uploadPhoto(Request $request, Employee $employee)
    {
        $user = $request->user();
        if ($user && $user->isEmployee() && $user->employee_id !== $employee->id) {
            abort(403, 'Forbidden');
        }

        $validated = $request->validate([
            'photo' => ['required', 'image', 'max:5120'],
        ]);

        $file = $validated['photo'];

        if ($employee->photo_path) {
            Storage::disk('public')->delete($employee->photo_path);
        }

        $employee->update([
            'photo_path' => $this->storePhoto($file, $employee->employee_code),
        ]);

        return new EmployeeResource($employee->fresh()->load('user'));
    }

    public function destroy(Employee $employee)
    {
        $request = request();
        $before = $employee->toArray();

        if ($employee->photo_path) {
            Storage::disk('public')->delete($employee->photo_path);
        }
        $employee->delete();

        $this->auditLogger->log($request->user(), 'employee.deleted', null, [
            'domain' => 'employee',
            'employee' => $before,
        ], $request);

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

    protected function storeEmployeeDocumentFile(Employee $employee, $file, string $type): string
    {
        $ext = $file->getClientOriginalExtension();
        $filename = sprintf('%s-%s.%s', $type, time(), $ext);
        $dir = sprintf('employee-documents/%s/%s', date('Y'), $employee->employee_code);

        return $file->storeAs($dir, $filename, ['disk' => 'public']);
    }
}