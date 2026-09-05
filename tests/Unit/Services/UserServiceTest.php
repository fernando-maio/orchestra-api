<?php

namespace Tests\Unit\Services;

use App\Contracts\Services\UserServiceInterface;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UserServiceTest extends TestCase
{
    private UserService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(UserServiceInterface::class);
    }

    // ──────────────────────────────────────────────────
    //  updateProfile
    // ──────────────────────────────────────────────────

    public function test_update_profile_changes_name_and_phone(): void
    {
        $user = User::factory()->create(['name' => 'Antigo', 'phone' => null]);

        $atualizado = $this->service->updateProfile($user, [
            'name' => 'Novo Nome',
            'phone' => '(11) 90000-0000',
        ]);

        $this->assertSame('Novo Nome', $atualizado->name);
        $this->assertSame('(11) 90000-0000', $atualizado->phone);
    }

    public function test_update_profile_discards_email_even_when_present(): void
    {
        // O FormRequest ja nao aceita o campo, mas o service e a ultima barreira
        // antes da escrita e nao pode depender de quem o chama.
        $user = User::factory()->create(['email' => 'original@example.com']);

        $this->service->updateProfile($user, [
            'name' => 'Nome',
            'email' => 'invasor@example.com',
        ]);

        $this->assertSame('original@example.com', $user->fresh()->email);
    }

    // ──────────────────────────────────────────────────
    //  changePassword
    // ──────────────────────────────────────────────────

    public function test_change_password_updates_the_hash(): void
    {
        $user = User::factory()->create(['password' => 'atual-123']);

        $this->service->changePassword($user, 'atual-123', 'nova-456');

        $this->assertTrue(Hash::check('nova-456', $user->fresh()->password));
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => 'atual-123']);

        $this->expectException(ValidationException::class);

        try {
            $this->service->changePassword($user, 'chute-errado', 'nova-456');
        } finally {
            // A senha nao pode ter mudado antes da excecao.
            $this->assertTrue(Hash::check('atual-123', $user->fresh()->password));
        }
    }

    public function test_change_password_revokes_all_tokens_when_none_is_preserved(): void
    {
        $user = User::factory()->create(['password' => 'atual-123']);
        $user->createToken('sessao-a');
        $user->createToken('sessao-b');

        $this->service->changePassword($user, 'atual-123', 'nova-456');

        $this->assertSame(0, $user->fresh()->tokens()->count());
    }

    public function test_change_password_preserves_the_informed_token(): void
    {
        $user = User::factory()->create(['password' => 'atual-123']);
        $user->createToken('sessao-antiga');
        $atual = $user->createToken('sessao-atual')->accessToken;

        $this->service->changePassword($user, 'atual-123', 'nova-456', $atual->getKey());

        $restantes = $user->fresh()->tokens()->pluck('name')->all();
        $this->assertSame(['sessao-atual'], $restantes);
    }

    public function test_change_password_does_not_revoke_tokens_when_current_password_is_wrong(): void
    {
        // A troca e a revogacao estao na mesma transacao: se a senha atual nao
        // confere, nada pode acontecer.
        $user = User::factory()->create(['password' => 'atual-123']);
        $user->createToken('sessao-a');

        try {
            $this->service->changePassword($user, 'errada', 'nova-456');
        } catch (ValidationException) {
            // esperado
        }

        $this->assertSame(1, $user->fresh()->tokens()->count());
    }
}
