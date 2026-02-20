<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login user and create token
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais informadas estão incorretas.'],
            ]);
        }

        if (!$user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Esta conta está desativada.'],
            ]);
        }

        // Check organization subscription
        if ($user->organization && !$user->organization->isSubscriptionActive()) {
            if (!$user->isSuperAdmin()) {
                throw ValidationException::withMessages([
                    'email' => ['A assinatura da sua organização está inativa.'],
                ]);
            }
        }

        $deviceName = $request->device_name ?? $request->userAgent() ?? 'unknown';
        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'message' => 'Login realizado com sucesso.',
            'data' => [
                'user' => $this->formatUser($user),
                'token' => $token,
            ],
        ]);
    }

    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'organization_name' => 'required|string|max:255',
        ]);

        // Create organization
        $organization = \App\Models\Organization::create([
            'name' => $request->organization_name,
            'email' => $request->email,
            'subscription_status' => 'trial',
            'subscription_ends_at' => now()->addDays(14),
        ]);

        // Create user
        $user = User::create([
            'organization_id' => $organization->id,
            'name' => $request->name,
            'email' => $request->email,
            'password' => $request->password,
        ]);

        // Assign admin role
        $user->assignRole('admin');

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'message' => 'Cadastro realizado com sucesso.',
            'data' => [
                'user' => $this->formatUser($user),
                'token' => $token,
            ],
        ], 201);
    }

    /**
     * Get current authenticated user
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $this->formatUser($user),
        ]);
    }

    /**
     * Logout current device
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logout realizado com sucesso.',
        ]);
    }

    /**
     * Logout all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'message' => 'Logout realizado em todos os dispositivos.',
        ]);
    }

    /**
     * Format user response
     */
    protected function formatUser(User $user): array
    {
        $user->load(['organization', 'roles', 'permissions']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar_path' => $user->avatar_path,
            'is_active' => $user->is_active,
            'organization' => $user->organization ? [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'subscription_status' => $user->organization->subscription_status,
                'subscription_plan' => $user->organization->subscription_plan,
            ] : null,
            'roles' => $user->roles->pluck('name'),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ];
    }
}
