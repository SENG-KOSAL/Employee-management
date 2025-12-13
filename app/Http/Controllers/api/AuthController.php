<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest; // optional, create request classes if desired
use App\Http\Resources\Users\UserResource;
use App\Models\User;
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
            'name' => 'required|string',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($request->only('name', 'password'))) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        /** @var User $user */
        $user = Auth::user();

        // Create a token for SPA use (optional) — if using cookie-based Sanctum, you may not issue tokens
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => new UserResource($user->load('employee')),
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
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:' . implode(',', User::ROLES),
            'employee_id' => 'nullable|exists:employees,id',
        ]);

        $user = User::create($data);

        return new UserResource($user->load('employee'));
    }
}