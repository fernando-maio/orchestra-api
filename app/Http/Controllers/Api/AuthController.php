<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Services\UserServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserServiceInterface $userService,
    ) {}

    /**
     * Login user and create token
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $resultado = $this->userService->authenticate(
            $request->validated('email'),
            $request->validated('password'),
            $request->deviceName(),
        );

        return response()->json([
            'message' => 'Login realizado com sucesso.',
            'data' => [
                'user' => $this->formatUser($resultado['user']),
                'token' => $resultado['token'],
            ],
        ]);
    }

    /**
     * Cria uma organização com seu primeiro administrador.
     *
     * ATENÇÃO: não há rota apontando para este método. O cadastro público está
     * desabilitado por decisão de produto, e como a organização se cadastra
     * ainda é questão em aberto (Fase 4.2 do roadmap). Mantido e corrigido
     * para não virar armadilha caso alguém decida roteá-lo.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $resultado = $this->userService->registerWithOrganization($request->validated());

        return response()->json([
            'message' => 'Cadastro realizado com sucesso.',
            'data' => [
                'user' => $this->formatUser($resultado['user']),
                'token' => $resultado['token'],
            ],
        ], 201);
    }

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
    /**
     * Atualiza o perfil do usuario autenticado.
     *
     * O e-mail nao e alteravel por aqui: e a credencial de login. A regra vale
     * em duas camadas - o UpdateProfileRequest nao aceita o campo, e o
     * UserService descarta caso ele chegue de qualquer forma.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $this->userService->updateProfile(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'message' => 'Perfil atualizado com sucesso.',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Troca a senha do usuario autenticado.
     */
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        // currentAccessToken() nem sempre e um PersonalAccessToken: em auth por
        // sessao vem um TransientToken, e pode vir null. Sem token identificado
        // nao ha o que preservar, e o service revoga todos.
        $currentToken = $user->currentAccessToken();
        $currentTokenId = $currentToken instanceof PersonalAccessToken
            ? $currentToken->getKey()
            : null;

        $this->userService->changePassword(
            $user,
            $request->validated('current_password'),
            $request->validated('password'),
            $currentTokenId,
        );

        return response()->json([
            'message' => 'Senha alterada com sucesso.',
        ]);
    }

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
