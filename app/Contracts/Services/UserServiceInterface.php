<?php

namespace App\Contracts\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

interface UserServiceInterface extends BaseServiceInterface
{
    /**
     * Autentica e emite token.
     *
     * @return array{user: User, token: string}
     *
     * @throws ValidationException
     */
    public function authenticate(string $email, string $password, string $deviceName): array;

    /**
     * Cria organizacao + primeiro administrador, numa transacao.
     *
     * @return array{user: User, token: string}
     */
    public function registerWithOrganization(array $data): array;

    /**
     * Atualiza os dados de perfil do usuario.
     *
     * O e-mail nao entra aqui: e a credencial de login, e troca-lo deve passar
     * por um fluxo proprio, com verificacao.
     */
    public function updateProfile(User $user, array $data): User;

    /**
     * Troca a senha do usuario, exigindo a senha atual.
     *
     * Revoga as demais sessoes e preserva a que fez a requisicao.
     *
     * @throws ValidationException se a senha atual nao confere
     */
    public function changePassword(
        User $user,
        string $currentPassword,
        string $newPassword,
        ?string $currentTokenId = null,
    ): User;
}
