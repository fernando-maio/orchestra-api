<?php

namespace Tests\Unit\Repositories;

use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Repositories\UserRepository;
use Tests\TestCase;

class UserRepositoryTest extends TestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = app(UserRepositoryInterface::class);
    }

    public function test_find_by_email_returns_the_user(): void
    {
        $user = User::factory()->create(['email' => 'alvo@example.com']);
        User::factory()->create(['email' => 'outro@example.com']);

        $this->assertSame($user->id, $this->repository->findByEmail('alvo@example.com')?->id);
    }

    public function test_find_by_email_returns_null_when_absent(): void
    {
        $this->assertNull($this->repository->findByEmail('naoexiste@example.com'));
    }

    public function test_revoke_tokens_except_keeps_the_informed_token(): void
    {
        $user = User::factory()->create();
        $user->createToken('antiga-1');
        $user->createToken('antiga-2');
        $atual = $user->createToken('atual')->accessToken;

        $removidos = $this->repository->revokeTokensExcept($user, $atual->getKey());

        $this->assertSame(2, $removidos);
        $this->assertSame(['atual'], $user->fresh()->tokens()->pluck('name')->all());
    }

    public function test_revoke_tokens_except_removes_all_when_no_token_informed(): void
    {
        $user = User::factory()->create();
        $user->createToken('a');
        $user->createToken('b');

        $removidos = $this->repository->revokeTokensExcept($user, null);

        $this->assertSame(2, $removidos);
        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_revoke_tokens_does_not_touch_other_users(): void
    {
        $user = User::factory()->create();
        $outro = User::factory()->create();
        $user->createToken('do-usuario');
        $outro->createToken('de-outro');

        $this->repository->revokeTokensExcept($user, null);

        $this->assertSame(1, $outro->fresh()->tokens()->count());
    }
}
