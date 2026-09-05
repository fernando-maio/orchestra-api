<?php

namespace App\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Services\UserServiceInterface;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService extends BaseService implements UserServiceInterface
{
    protected UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $repository)
    {
        parent::__construct($repository);
        $this->userRepository = $repository;
    }

    /**
     * Autentica por e-mail e senha e emite um token.
     *
     * As tres recusas (credencial errada, conta desativada, assinatura
     * inativa) usam a mesma chave 'email' de proposito: a mensagem muda, mas
     * o campo destacado no formulario e sempre o mesmo.
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function authenticate(string $email, string $password, string $deviceName): array
    {
        $user = $this->userRepository->findByEmail($email);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais informadas estão incorretas.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Esta conta está desativada.'],
            ]);
        }

        // O super-admin entra mesmo com a assinatura da organizacao inativa:
        // e quem precisa acessar a plataforma justamente para resolver isso.
        if ($user->organization
            && ! $user->organization->isSubscriptionActive()
            && ! $user->isSuperAdmin()
        ) {
            throw ValidationException::withMessages([
                'email' => ['A assinatura da sua organização está inativa.'],
            ]);
        }

        return [
            'user' => $user,
            'token' => $user->createToken($deviceName)->plainTextToken,
        ];
    }

    /**
     * Cria uma organizacao com seu primeiro usuario administrador.
     *
     * ATENCAO: nao ha rota apontando para isto. O cadastro publico esta
     * desabilitado por decisao de produto, e como a organizacao se cadastra e
     * uma questao ainda em aberto (Fase 4.2 do roadmap).
     *
     * @return array{user: User, token: string}
     */
    public function registerWithOrganization(array $data): array
    {
        // Organizacao, usuario e papel precisam nascer juntos: uma falha no
        // meio deixaria uma organizacao orfa, sem ninguem que consiga acessa-la.
        return DB::transaction(function () use ($data) {
            $organization = Organization::create([
                'name' => $data['organization_name'],
                'email' => $data['email'],
                'subscription_status' => 'trial',
                'subscription_ends_at' => now()->addDays(14),
            ]);

            $user = User::create([
                'organization_id' => $organization->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->assignRole('admin');

            return [
                'user' => $user,
                'token' => $user->createToken('api')->plainTextToken,
            ];
        });
    }

    public function updateProfile(User $user, array $data): User
    {
        // Blindagem contra alteracao de e-mail. O FormRequest ja nao aceita o
        // campo, mas o service e o ultimo ponto antes da escrita e nao pode
        // depender de quem o chama ter validado direito.
        unset($data['email']);

        $user->update($data);

        return $user->fresh()->load('organization');
    }

    public function changePassword(
        User $user,
        string $currentPassword,
        string $newPassword,
        ?string $currentTokenId = null,
    ): User {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['A senha atual está incorreta.'],
            ]);
        }

        // A troca da senha e a revogacao das sessoes precisam acontecer juntas:
        // senha nova com sessao antiga ainda valida seria pior que nao trocar.
        return DB::transaction(function () use ($user, $newPassword, $currentTokenId) {
            $user->update(['password' => $newPassword]);

            $this->userRepository->revokeTokensExcept($user, $currentTokenId);

            return $user->fresh();
        });
    }
}
