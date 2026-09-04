<?php

namespace Tests\Feature\Api;

use App\Models\User;
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
