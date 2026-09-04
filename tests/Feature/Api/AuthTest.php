<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    // ─── LOGIN ────────────────────────────────────────────────────────

    public function test_login_with_valid_credentials(): void
    {
        $org = $this->createOrganization();
        $user = User::factory()->forOrganization($org)->create([
            'email' => 'user@example.com',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email', 'is_active', 'organization', 'roles', 'permissions'],
                    'token',
                ],
            ])
            ->assertJsonPath('data.user.email', 'user@example.com');
    }

    public function test_login_with_invalid_credentials(): void
    {
        $org = $this->createOrganization();
        User::factory()->forOrganization($org)->create([
            'email' => 'user@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'secret123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_inactive_user_is_rejected(): void
    {
        $org = $this->createOrganization();
        User::factory()->forOrganization($org)->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => 'secret123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactive@example.com',
            'password' => 'secret123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->postJson('/api/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_with_inactive_organization_subscription_blocks_non_super_admin(): void
    {
        $org = $this->createOrganization([
            'subscription_status' => 'canceled',
            'subscription_ends_at' => now()->subDay(),
        ]);
        $user = User::factory()->forOrganization($org)->create([
            'email' => 'org-user@example.com',
            'password' => 'secret123',
        ]);
        $user->assignRole('admin');

        $response = $this->postJson('/api/login', [
            'email' => 'org-user@example.com',
            'password' => 'secret123',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('email');
    }

    public function test_login_super_admin_bypasses_org_subscription_check(): void
    {
        $org = $this->createOrganization([
            'subscription_status' => 'canceled',
        ]);
        $user = User::factory()->forOrganization($org)->create([
            'email' => 'super@example.com',
            'password' => 'secret123',
        ]);
        $user->assignRole('super-admin');

        $response = $this->postJson('/api/login', [
            'email' => 'super@example.com',
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.email', 'super@example.com');
    }

    // ─── ME ───────────────────────────────────────────────────────────

    // ─── PERFIL ───────────────────────────────────────────────────────

    public function test_update_profile_changes_name(): void
    {
        $user = $this->actingAsSuperAdmin();

        $this->putJson('/api/auth/profile', ['name' => 'Nome Novo'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nome Novo');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nome Novo']);
    }

    public function test_update_profile_never_changes_email(): void
    {
        // A tela deixa o campo readonly, mas a regra tem que valer tambem para
        // quem chamar a API direto.
        $user = $this->actingAsSuperAdmin();
        $emailOriginal = $user->email;

        $this->putJson('/api/auth/profile', [
            'name' => 'Nome Novo',
            'email' => 'invasor@example.com',
        ])->assertOk();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $emailOriginal]);
        $this->assertDatabaseMissing('users', ['email' => 'invasor@example.com']);
    }

    public function test_update_profile_requires_name(): void
    {
        $this->actingAsSuperAdmin();

        $this->putJson('/api/auth/profile', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_update_profile_requires_authentication(): void
    {
        $this->putJson('/api/auth/profile', ['name' => 'X'])->assertUnauthorized();
    }

    // ─── SENHA ────────────────────────────────────────────────────────

    public function test_update_password_changes_password(): void
    {
        $user = User::factory()->create(['password' => 'senha-atual-123']);
        $this->actingAs($user, 'sanctum');

        $this->putJson('/api/auth/password', [
            'current_password' => 'senha-atual-123',
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertOk();

        $this->assertTrue(Hash::check('senha-nova-456', $user->fresh()->password));
    }

    public function test_update_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password' => 'senha-atual-123']);
        $this->actingAs($user, 'sanctum');

        $this->putJson('/api/auth/password', [
            'current_password' => 'chute-errado',
            'password' => 'senha-nova-456',
            'password_confirmation' => 'senha-nova-456',
        ])->assertStatus(422)->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('senha-atual-123', $user->fresh()->password));
    }

    public function test_update_password_requires_confirmation(): void
    {
        $user = User::factory()->create(['password' => 'senha-atual-123']);
        $this->actingAs($user, 'sanctum');

        $this->putJson('/api/auth/password', [
            'current_password' => 'senha-atual-123',
            'password' => 'senha-nova-456',
            'password_confirmation' => 'outra-coisa',
        ])->assertStatus(422)->assertJsonValidationErrors('password');
    }

    public function test_update_password_revokes_other_sessions_but_keeps_the_current_one(): void
    {
        // Trocar a senha nao pode deixar sessoes antigas ativas - mas quem
        // acabou de trocar nao pode ser deslogado no meio do caminho.
        // Usa token real (e nao actingAs) porque e justamente o
        // currentAccessToken() que decide qual token sobrevive.
        $user = User::factory()->create(['password' => 'senha-atual-123']);
        $user->createToken('sessao-antiga');
        $atual = $user->createToken('sessao-atual')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$atual}")
            ->putJson('/api/auth/password', [
                'current_password' => 'senha-atual-123',
                'password' => 'senha-nova-456',
                'password_confirmation' => 'senha-nova-456',
            ])->assertOk();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'sessao-antiga',
        ]);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'sessao-atual',
        ]);
    }

    // ─── RATE LIMITING ────────────────────────────────────────────────

    public function test_login_is_rate_limited_after_configured_attempts(): void
    {
        config(['auth.login_max_attempts' => 3]);

        $payload = ['email' => 'naoexiste@example.com', 'password' => 'errada'];

        // As tentativas dentro do limite devem responder 422 (credenciais
        // invalidas), nunca 429.
        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/login', $payload)
                ->assertStatus(422, "tentativa {$i} deveria passar pelo limiter");
        }

        // A seguinte estoura o limite.
        $this->postJson('/api/login', $payload)->assertStatus(429);
    }

    public function test_login_allows_five_attempts_before_throttling_by_default(): void
    {
        // Protege o limite que vale em producao. O phpunit.xml fixa
        // LOGIN_MAX_ATTEMPTS=5 para que este teste nao dependa do .env da
        // maquina de quem roda a suite.
        $payload = ['email' => 'naoexiste@example.com', 'password' => 'errada'];

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/login', $payload)
                ->assertStatus(422, "tentativa {$i} de 5 nao deveria ser bloqueada");
        }

        $this->postJson('/api/login', $payload)->assertStatus(429);
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = $this->actingAsSuperAdmin();

        $response = $this->getJson('/api/me');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['id', 'name', 'email', 'roles', 'permissions'],
            ])
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_me_requires_authentication(): void
    {
        $response = $this->getJson('/api/me');

        $response->assertUnauthorized();
    }

    // ─── LOGOUT ───────────────────────────────────────────────────────

    public function test_logout_revokes_current_token(): void
    {
        $org = $this->createOrganization();
        $user = User::factory()->forOrganization($org)->create([
            'email' => 'logout@example.com',
            'password' => 'secret123',
        ]);
        $user->assignRole('admin');

        // Login via API to get a real token
        $loginResponse = $this->postJson('/api/login', [
            'email' => 'logout@example.com',
            'password' => 'secret123',
        ]);
        $token = $loginResponse->json('data.token');

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$token}",
        ])->postJson('/api/logout');

        $response->assertOk()
            ->assertJsonPath('message', 'Logout realizado com sucesso.');
    }

    public function test_logout_requires_authentication(): void
    {
        $response = $this->postJson('/api/logout');

        $response->assertUnauthorized();
    }

    // ─── LOGOUT ALL ───────────────────────────────────────────────────

    public function test_logout_all_revokes_all_tokens(): void
    {
        $user = $this->actingAsSuperAdmin();

        $response = $this->postJson('/api/logout-all');

        $response->assertOk()
            ->assertJsonPath('message', 'Logout realizado em todos os dispositivos.');
    }

    // ─── UNAUTHENTICATED ACCESS ───────────────────────────────────────

    public function test_protected_routes_require_authentication(): void
    {
        $endpoints = [
            ['GET', '/api/me'],
            ['POST', '/api/logout'],
            ['POST', '/api/logout-all'],
            ['GET', '/api/dashboard'],
            ['GET', '/api/categories'],
            ['GET', '/api/vendors'],
            ['GET', '/api/events'],
        ];

        foreach ($endpoints as [$method, $uri]) {
            $response = $this->json($method, $uri);
            $response->assertUnauthorized();
        }
    }
}
