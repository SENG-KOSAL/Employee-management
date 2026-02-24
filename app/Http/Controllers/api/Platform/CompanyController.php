<?php

namespace App\Http\Controllers\Api\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $companies = Company::query()
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        return CompanyResource::collection($companies);
    }

    public function store(Request $request)
    {
        if ($request->filled('slug')) {
            $request->merge(['slug' => strtolower((string) $request->input('slug'))]);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:companies,slug',
            'modules_enabled' => 'nullable|array',
            'settings' => 'nullable|array',

            // Optional: create an initial company admin along with the company.
            'admin' => 'nullable|array',
            'admin.name' => 'required_with:admin|string|max:255',
            'admin.email' => 'required_with:admin|email|unique:users,email',
            'admin.password' => 'required_with:admin|string|min:8',
            'admin.username' => 'nullable|string|max:255|unique:users,username',
        ]);

        $admin = null;

        $company = DB::transaction(function () use ($data, &$admin) {
            $company = Company::create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?? Str::slug($data['name']),
                'modules_enabled' => $data['modules_enabled'] ?? [],
                'settings' => $data['settings'] ?? [],
                'status' => 'active',
            ]);

            if (! empty($data['admin'])) {
                $adminData = $data['admin'];

                $username = $adminData['username']
                    ?? $this->makeUsernameFromEmail($adminData['email']);

                $admin = User::create([
                    'name' => $adminData['name'],
                    'username' => $username,
                    'email' => $adminData['email'],
                    // User model hashes automatically via mutator.
                    'password' => $adminData['password'],
                    'role' => 'company_admin',
                    'company_id' => $company->id,
                    'status' => 'active',
                ]);
            }

            return $company;
        });

        return (new CompanyResource($company))->additional([
            'admin' => $admin ? [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'username' => $admin->username,
                'role' => $admin->role,
                'company_id' => $admin->company_id,
            ] : null,
        ]);
    }

    private function makeUsernameFromEmail(string $email): string
    {
        $local = explode('@', $email)[0] ?? 'companyadmin';
        $base = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $local));
        $base = trim($base, '_') ?: 'companyadmin';

        $candidate = $base;
        $suffix = 1;
        while (User::query()->where('username', $candidate)->exists()) {
            $suffix++;
            $candidate = $base . '_' . $suffix;
        }

        return $candidate;
    }

    public function show(Company $company)
    {
        return new CompanyResource($company);
    }

    public function update(Request $request, Company $company)
    {
        if ($request->filled('slug')) {
            $request->merge(['slug' => strtolower((string) $request->input('slug'))]);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|unique:companies,slug,' . $company->id,
            'status' => 'sometimes|in:active,suspended',
            'modules_enabled' => 'nullable|array',
            'settings' => 'nullable|array',
        ]);

        $company->fill($data);

        if (isset($data['status']) && $data['status'] === 'active') {
            $company->suspended_at = null;
        } elseif (isset($data['status']) && $data['status'] === 'suspended') {
            $company->suspended_at = now();
        }

        $company->save();

        return new CompanyResource($company);
    }

    public function enable(Company $company)
    {
        $company->update([
            'status' => 'active',
            'suspended_at' => null,
        ]);

        return new CompanyResource($company);
    }

    public function suspend(Company $company)
    {
        $company->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        return new CompanyResource($company);
    }

    public function createAdmin(Request $request, Company $company)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'username' => 'nullable|string|max:255|unique:users,username',
        ]);

        $username = $data['username'] ?? $this->makeUsernameFromEmail($data['email']);

        $user = User::create([
            'name' => $data['name'],
            'username' => $username,
            'email' => $data['email'],
            // User model hashes automatically via mutator.
            'password' => $data['password'],
            'company_id' => $company->id,
            'role' => 'company_admin',
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Client admin ready',
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);
    }

    public function destroy(Request $request, Company $company)
    {
        $actor = $request->user();

        $data = $request->validate([
            'current_password' => 'required|string',
            // Verification: caller must explicitly confirm the exact company slug.
            'confirm_slug' => 'required|string',
            // Optional extra guard for UX confirmation prompts.
            'confirm_text' => 'nullable|string',
        ]);

        if (! Hash::check($data['current_password'], $actor->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Current password is incorrect.'],
            ]);
        }

        if (strtolower((string) $data['confirm_slug']) !== strtolower((string) $company->slug)) {
            throw ValidationException::withMessages([
                'confirm_slug' => ['Confirmation slug does not match this company slug.'],
            ]);
        }

        if (isset($data['confirm_text']) && strtoupper((string) $data['confirm_text']) !== 'DELETE') {
            throw ValidationException::withMessages([
                'confirm_text' => ['confirm_text must be DELETE when provided.'],
            ]);
        }

        DB::transaction(function () use ($company) {
            // Remove company users explicitly to avoid orphaned tenant-role accounts.
            User::query()->where('company_id', $company->id)->delete();

            // Tenant tables use cascadeOnDelete on company_id.
            $company->delete();
        });

        return response()->json([
            'message' => 'Company deleted successfully.',
            'deleted_company_id' => $company->id,
            'deleted_company_slug' => $company->slug,
        ]);
    }
}
