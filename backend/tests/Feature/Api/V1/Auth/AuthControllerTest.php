<?php

namespace Tests\Feature\Api\V1\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Limpa cache de throttle entre testes
        Cache::flush();

        $this->tenant = Tenant::factory()->create();
    }

    private function createActiveUser(string $email = 'auth@test.com', string $password = 'StrongPass!123', bool $active = true): User
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => $active,
        ]);
        $user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        return $user;
    }

    public function test_login_validates_required_email_and_password(): void
    {
        $response = $this->postJson('/api/v1/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $this->createActiveUser('valid@test.com', 'CorrectPass!1');

        $response = $this->postJson('/api/v1/login', [
            'email' => 'valid@test.com',
            'password' => 'WrongPassword',
        ]);

        // AuthController lanca ValidationException para credenciais invalidas
        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $this->createActiveUser('success@test.com', 'CorrectPass!1');

        $response = $this->postJson('/api/v1/login', [
            'email' => 'success@test.com',
            'password' => 'CorrectPass!1',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'email'],
                ],
            ]);

        $this->assertNotEmpty($response->json('data.token'), 'Token deve ser retornado no login');
    }

    public function test_login_blocks_disabled_account(): void
    {
        $this->createActiveUser('inactive@test.com', 'CorrectPass!1', active: false);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'inactive@test.com',
            'password' => 'CorrectPass!1',
        ]);

        $response->assertStatus(403);
    }

    public function test_login_is_case_insensitive_on_email(): void
    {
        $this->createActiveUser('casecheck@test.com', 'CorrectPass!1');

        $response = $this->postJson('/api/v1/login', [
            'email' => 'CaseCheck@Test.COM',
            'password' => 'CorrectPass!1',
        ]);

        $response->assertOk();
    }

    public function test_me_returns_authenticated_user(): void
    {
        $user = $this->createActiveUser('me@test.com', 'CorrectPass!1');
        Sanctum::actingAs($user, ['*']);

        $response = $this->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id);
    }

    public function test_logout_invalidates_current_token(): void
    {
        $user = $this->createActiveUser('logout@test.com', 'CorrectPass!1');
        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/logout');

        $response->assertOk();
    }
}
