<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Users\UserResource;
use App\Models\Company;
use App\Models\User;
use App\Support\ActiveCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
class AuthController extends Controller
{
    use AuthorizesRequests;

    // Return current authenticated user with optional employee relation
    // Return current authenticated user with optional employee relation
    public function me(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user->load('employee');

        return new UserResource($user);
    }

    // Login using email and password
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($request->only('username', 'password'))) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        /** @var User $user */
        $user = Auth::user();

        // Tenant-domain login enforcement for non-superadmin users.
        // Example: z-company.localhost -> slug "z-company".
        if (! $user->isSuperAdmin()) {
            $forwardedHost = $request->header('X-Forwarded-Host')
                ?: $request->header('X-Original-Host')
                ?: $request->header('X-Tenant-Host');

            // If running behind a frontend proxy, the backend may see Host=localhost.
            // In that case, Origin/Referer usually contains the real tenant domain.
            $originHost = null;
            if ($origin = $request->header('Origin')) {
                $originHost = parse_url($origin, PHP_URL_HOST);
            }
            if (! $originHost && ($referer = $request->header('Referer'))) {
                $originHost = parse_url($referer, PHP_URL_HOST);
            }

            $host = (string) ($forwardedHost ?: $originHost ?: $request->getHost());
            $host = trim(explode(',', $host)[0]);
            $host = preg_replace('/:\\d+$/', '', $host) ?? $host;
            $hostSlug = strtolower((string) strtok($host, '.'));

            // For tenant users, a tenant host must map to an active company.
            if ($hostSlug === '' || in_array($hostSlug, ['localhost', '127', '0'], true)) {
                Auth::logout();

                $extra = [];
                if (config('app.debug')) {
                    $extra['debug'] = [
                        'detected_host' => $host,
                        'detected_slug' => $hostSlug,
                        'request_host' => $request->getHost(),
                        'x_forwarded_host' => $request->header('X-Forwarded-Host'),
                        'x_original_host' => $request->header('X-Original-Host'),
                        'x_tenant_host' => $request->header('X-Tenant-Host'),
                        'origin' => $request->header('Origin'),
                        'referer' => $request->header('Referer'),
                    ];
                }

                return response()->json(array_merge([
                    'message' => 'Tenant users must sign in from their company domain.',
                ], $extra), 403);
            }

            $hostCompany = Company::query()->active()->where('slug', $hostSlug)->first();
            if (! $hostCompany) {
                Auth::logout();

                $extra = [];
                if (config('app.debug')) {
                    $extra['debug'] = [
                        'detected_host' => $host,
                        'detected_slug' => $hostSlug,
                        'request_host' => $request->getHost(),
                    ];
                }

                return response()->json(array_merge([
                    'message' => 'Company domain is invalid or not active.',
                ], $extra), 403);
            }

            if ((int) $user->company_id !== (int) $hostCompany->id) {
                Auth::logout();

                return response()->json([
                    'message' => 'This account is not allowed to sign in on this domain.',
                ], 403);
            }
        }

        if ($user->status !== 'active') {
            Auth::logout();

            return response()->json(['message' => 'Account is suspended'], 403);
        }

        $user->markLastLogin();

        // Create a token for SPA use (optional) — if using cookie-based Sanctum, you may not issue tokens
        $abilities = $user->isSuperAdmin() ? ['platform'] : ['tenant'];
        $token = $user->createToken('api-token', $abilities)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load(['employee', 'company'])),
        ]);
    }

    // Example logout: delete tokens (for token-based)
    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            // Revoke the token used for this request
            $user->currentAccessToken()?->delete();
        }

        return response()->json(['message' => 'Logged out']);
    }

    // Optional: Admin-only create user endpoint
    public function createUser(Request $request)
    {
        $this->authorize('create-user');

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:' . implode(',', User::ROLES),
            'company_id' => 'nullable|exists:companies,id',
            'employee_id' => 'nullable|exists:employees,id',
            'status' => 'nullable|in:active,suspended',
        ]);

        $activeCompany = app(ActiveCompany::class);

        if (! auth()->user()->isSuperAdmin()) {
            $data['company_id'] = $activeCompany->id();
        }

        // Super admin accounts are always global (no company).
        if ($data['role'] === 'super_admin') {
            $data['company_id'] = null;
        }

        $user = User::create($data);

        return new UserResource($user->load(['employee', 'company']));
    }
}