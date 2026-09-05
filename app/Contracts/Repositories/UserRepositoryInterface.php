<?php

namespace App\Contracts\Repositories;

use App\Models\User;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    /**
     * Revoga todos os tokens do usuario, exceto o informado.
     *
     * @param  string|null  $exceptTokenId  quando null, revoga todos
     */
    public function revokeTokensExcept(User $user, ?string $exceptTokenId = null): int;
}
