<?php

namespace App\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    public function revokeTokensExcept(User $user, ?string $exceptTokenId = null): int
    {
        return $user->tokens()
            ->when($exceptTokenId, fn ($query) => $query->where('id', '!=', $exceptTokenId))
            ->delete();
    }
}
