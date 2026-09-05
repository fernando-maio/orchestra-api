<?php

namespace App\Services;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Services\UserServiceInterface;
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
