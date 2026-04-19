<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Auth Security Tests — validates authentication, token management,
 * brute force protection, tenant switching, password flows, and audit logging.
 *
 * Tests run WITH real middleware to validate production-level security behavior.
 */
class AuthSecurityTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

    private Tenant $inactiveTenant;

    private User $activeUser;

    private User $inactiveUser;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->tenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $this->otherTenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $this->inactiveTenant = Tenant::factory()->create(['status' => Tenant::STATUS_INACTIVE]);

        $this->activeUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'email' => 'active@test.com',
            'password' => Hash::make('senha1234'),
            'is_active' => true,
        ]);
        $this->activeUser->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->inactiveUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'email' => 'inactive@test.com',
            'password' => Hash::make('senha1234'),
            'is_active' => false,
        ]);
        $this->inactiveUser->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
    }

    // ══════════════════════════════════════════════
    // ── LOGIN — HAPPY PATH
    // ══════════════════════════════════════════════

    public function test_login_with_valid_credentials_returns_token(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'active@test.com',
            'password' => 'senha1234',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => ['id', 'name', 'email', 'tenant_id', 'permissions', 'roles', 'role_details'],
                ],
            ]);

        $payload = $response->json('data');
        $this->assertNotEmpty($payload['token']);
        $this->assertEquals($this->activeUser->id, $payload['user']['id']);
        $this->assertEquals($this->tenant->id, $payload['user']['tenant_id']);
        $token = PersonalAccessToken::findToken($payload['token']);
        $this->assertNotNull($token);
        $this->assertContains("tenant:{$this->tenant->id}", $token->abilities);
    }

    public function test_login_updates_last_login_at(): void
    {
        $this->assertNull($this->activeUser->last_login_at);

        $this->postJson('/api/v1/login', [
            'email' => 'active@test.com',
            'password' => 'senha1234',
        ])->assertOk();

        $this->activeUser->refresh();
        $this->assertNotNull($this->activeUser->last_login_at);
    }

    public function test_login_revokes_previous_api_tokens(): void
    {
        // Create some existing tokens
        $this->activeUser->createToken('api');
        $this->activeUser->createToken('api');
        $this->assertGreaterThanOrEqual(2, $this->activeUser->tokens()->where('name', 'api')->count());

        $this->postJson('/api/v1/login', [
            'email' => 'active@test.com',
            'password' => 'senha1234',
        ])->assertOk();

        // After login, only the new token should remain
        $this->assertEquals(1, $this->activeUser->tokens()->where('name', 'api')->count());
    }

    public function test_login_sets_default_tenant_if_no_current(): void
    {
        $this->activeUser->forceFill(['current_tenant_id' => null])->save();

        $response = $this->postJson('/api/v1/login', [
            'email' => 'active@test.com',
            'password' => 'senha1234',
        ]);

        $response->assertOk();
        $this->activeUser->refresh();
        $this->assertEquals($this->tenant->id, $this->activeUser->current_tenant_id);
    }

    public function test_login_uses_user_tenant_when_no_current_and_no_default_relation(): void
    {
        $standaloneTenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $standaloneUser = User::factory()->create([
            'tenant_id' => $standaloneTenant->id,
            'current_tenant_id' => null,
            'email' => 'standalone@test.com',
            'password' => Hash::make('senha1234'),
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'standalone@test.com',
            'password' => 'senha1234',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.tenant_id', $standaloneTenant->id);

        $standaloneUser->refresh();
        $this->assertEquals($standaloneTenant->id, $standaloneUser->current_tenant_id);

        $token = PersonalAccessToken::findToken($response->json('data.token'));
        $this->assertNotNull($token);
        $this->assertContains("tenant:{$standaloneTenant->id}", $token->abilities);
    }

    // ══════════════════════════════════════════════
    // ── LOGIN — EMAIL CASE SENSITIVITY (AUTH-006)
    // ══════════════════════════════════════════════

    public function test_login_is_case_insensitive_for_email(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'Active@Test.COM',
            'password' => 'senha1234',
        ]);

        $response->assertOk();
        $this->assertEquals($this->activeUser->id, $response->json('data.user.id'));
    }

    public function test_throttle_key_shares_across_email_cases(): void
    {
        // 3 attempts with lowercase
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => 'active@test.com',
                'password' => 'wrong',
            ]);
        }

        // 2 more with mixed case — should still count toward the same throttle
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => 'Active@Test.COM',
                'password' => 'wrong',
            ]);
        }

        // 6th attempt: should be blocked
        $response = $this->postJson('/api/v1/login', [
            'email' => 'ACTIVE@TEST.COM',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
        $this->assertStringContainsString('Muitas tentativas', $response->json('message'));
    }

    // ══════════════════════════════════════════════
    // ── LOGIN — INACTIVE TENANT FALLBACK
    // ══════════════════════════════════════════════

    public function test_login_with_inactive_current_tenant_falls_back_to_active(): void
    {
        // Attach user to inactive tenant as current, and active tenant as alternative
        $this->activeUser->forceFill(['current_tenant_id' => $this->inactiveTenant->id])->save();
        $this->activeUser->tenants()->attach($this->inactiveTenant->id, ['is_default' => false]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'active@test.com',
            'password' => 'senha1234',
        ]);

        $response->assertOk();
        $this->activeUser->refresh();

        // Should have switched to the active tenant
        $this->assertNotEquals($this->inactiveTenant->id, $this->activeUser->current_tenant_id);
        $this->assertEquals($this->tenant->id, $this->activeUser->current_tenant_id);
    }

    // ══════════════════════════════════════════════
    // ── LOGIN — FAILURES
    // ══════════════════════════════════════════════

    public function test_login_with_wrong_password_returns_422(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'active@test.com',
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_with_nonexistent_email_returns_422(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nobody@nowhere.com',
            'password' => 'senha1234',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_with_inactive_user_returns_403(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'inactive@test.com',
            'password' => 'senha1234',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Conta desativada.');
    }

    public function test_login_without_email_returns_422(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'password' => 'senha1234',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_without_password_returns_422(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'active@test.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_login_with_invalid_email_format_returns_422(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'not-an-email',
            'password' => 'senha1234',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    // ══════════════════════════════════════════════
    // ── BRUTE FORCE PROTECTION
    // ══════════════════════════════════════════════

    public function test_login_rate_limiting_blocks_after_5_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => 'active@test.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->postJson('/api/v1/login', [
            'email' => 'active@test.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
        $this->assertStringContainsString('Muitas tentativas', $response->json('message'));
    }

    public function test_rate_limiting_blocks_even_with_correct_password(): void
    {
        // Exhaust attempts with wrong password
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => 'active@test.com',
                'password' => 'wrong',
            ]);
        }

        // Correct password should STILL be blocked
        $response = $this->postJson('/api/v1/login', [
            'email' => 'active@test.com',
            'password' => 'senha1234',
        ]);

        $response->assertStatus(429);
    }

    public function test_successful_login_clears_failed_attempts(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => 'active@test.com',
                'password' => 'wrong',
            ]);
        }

        // Successful login should clear counter
        $this->postJson('/api/v1/login', [
            'email' => 'active@test.com',
            'password' => 'senha1234',
        ])->assertOk();

        // 4 more failed attempts should NOT trigger block (counter was reset)
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => 'active@test.com',
                'password' => 'wrong',
            ])->assertStatus(422);
        }
    }

    // ══════════════════════════════════════════════
    // ── PROTECTED ROUTES WITHOUT TOKEN
    // ══════════════════════════════════════════════

    public function test_access_me_without_token_returns_401(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_access_protected_route_without_token_returns_401(): void
    {
        $this->getJson('/api/v1/customers')->assertUnauthorized();
    }

    public function test_post_logout_without_token_returns_401(): void
    {
        $this->postJson('/api/v1/logout')->assertUnauthorized();
    }

    // ══════════════════════════════════════════════
    // ── LOGOUT
    // ══════════════════════════════════════════════

    public function test_logout_invalidates_token(): void
    {
        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => 'active@test.com',
            'password' => 'senha1234',
        ]);
        $token = $loginResponse->json('data.token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logout realizado.');

        // Reset auth guard cache (PHPUnit runs in same process)
        $this->app['auth']->forgetGuards();

        // Token should be invalid
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_logout_does_not_invalidate_other_sessions(): void
    {
        // Create two independent tokens
        $token1 = $this->activeUser->createToken('api')->plainTextToken;
        $token2 = $this->activeUser->createToken('api')->plainTextToken;

        // Logout with token1
        $this->withToken($token1)
            ->postJson('/api/v1/logout')
            ->assertOk();

        // Token2 should STILL work
        $this->withToken($token2)
            ->getJson('/api/v1/me')
            ->assertOk();
    }

    // ══════════════════════════════════════════════
    // ── ME ENDPOINT
    // ══════════════════════════════════════════════

    public function test_me_returns_authenticated_user_data(): void
    {
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['user' => ['id', 'name', 'email', 'phone', 'permissions', 'roles', 'role_details', 'last_login_at']],
            ])
            ->assertJsonPath('data.user.id', $this->activeUser->id)
            ->assertJsonPath('data.user.email', 'active@test.com');
    }

    public function test_me_does_not_expose_password(): void
    {
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->getJson('/api/v1/me');
        $responseBody = $response->json();

        $this->assertArrayNotHasKey('password', $responseBody['data']['user']);
        $this->assertStringNotContainsString('password', json_encode($responseBody));
    }

    public function test_me_includes_tenant_data(): void
    {
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['user' => ['tenant']]]);

        $this->assertNotNull($response->json('data.user.tenant'));
    }

    public function test_me_returns_role_details_array(): void
    {
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->getJson('/api/v1/me');
        $roleDetails = $response->json('data.user.role_details');

        $this->assertIsArray($roleDetails);
    }

    // ══════════════════════════════════════════════
    // ── TENANT SWITCHING
    // ══════════════════════════════════════════════

    public function test_switch_tenant_changes_context(): void
    {
        $this->activeUser->tenants()->attach($this->otherTenant->id, ['is_default' => false]);
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->postJson('/api/v1/switch-tenant', [
            'tenant_id' => $this->otherTenant->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.tenant_id', $this->otherTenant->id)
            ->assertJsonStructure(['data' => ['token']]);

        $this->activeUser->refresh();
        $this->assertEquals($this->otherTenant->id, $this->activeUser->current_tenant_id);
        $token = PersonalAccessToken::findToken($response->json('data.token'));
        $this->assertNotNull($token);
        $this->assertContains("tenant:{$this->otherTenant->id}", $token->abilities);
    }

    public function test_switch_tenant_to_unauthorized_tenant_returns_403(): void
    {
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->postJson('/api/v1/switch-tenant', [
            'tenant_id' => $this->otherTenant->id, // Not attached
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Acesso negado a esta empresa.');
    }

    public function test_switch_tenant_to_inactive_tenant_returns_403(): void
    {
        $this->activeUser->tenants()->attach($this->inactiveTenant->id, ['is_default' => false]);
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->postJson('/api/v1/switch-tenant', [
            'tenant_id' => $this->inactiveTenant->id,
        ]);

        $response->assertForbidden()
            ->assertJsonPath('message', 'Esta empresa está inativa. Contate o administrador.');
    }

    public function test_switch_tenant_to_nonexistent_returns_404(): void
    {
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->postJson('/api/v1/switch-tenant', [
            'tenant_id' => 999999,
        ]);

        // Should be 403 (access denied) since user doesn't have access
        $response->assertForbidden();
    }

    public function test_switch_tenant_without_tenant_id_returns_422(): void
    {
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->postJson('/api/v1/switch-tenant', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('tenant_id');
    }

    public function test_my_tenants_returns_only_accessible_tenants(): void
    {
        $this->activeUser->tenants()->attach($this->otherTenant->id, ['is_default' => false]);
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->getJson('/api/v1/my-tenants');

        $response->assertOk();

        $tenants = collect($response->json('data'));
        $tenantIds = $tenants->pluck('id')->toArray();
        $this->assertContains($this->tenant->id, $tenantIds);
        $this->assertContains($this->otherTenant->id, $tenantIds);
    }

    public function test_my_tenants_returns_correct_fields(): void
    {
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->getJson('/api/v1/my-tenants');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'status']]]);
    }

    // ══════════════════════════════════════════════
    // ── CHANGE PASSWORD
    // ══════════════════════════════════════════════

    public function test_change_password_with_wrong_current_fails(): void
    {
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->postJson('/api/v1/profile/change-password', [
            'current_password' => 'wrong_password',
            'new_password' => 'Newpassword123!',
            'new_password_confirmation' => 'Newpassword123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Senha atual incorreta.');
    }

    public function test_change_password_with_valid_data_succeeds(): void
    {
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->postJson('/api/v1/profile/change-password', [
            'current_password' => 'senha1234',
            'new_password' => 'Newpassword123!',
            'new_password_confirmation' => 'Newpassword123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Senha alterada com sucesso.');

        $this->activeUser->refresh();
        $this->assertTrue(Hash::check('Newpassword123!', $this->activeUser->password));
    }

    public function test_change_password_revokes_other_sessions(): void
    {
        $token = $this->activeUser->createToken('session-main')->plainTextToken;
        $this->activeUser->createToken('session-2');
        $this->activeUser->createToken('session-3');
        $this->assertGreaterThanOrEqual(2, $this->activeUser->tokens()->count());

        $this->withToken($token)
            ->postJson('/api/v1/profile/change-password', [
                'current_password' => 'senha1234',
                'new_password' => 'Newpassword123!',
                'new_password_confirmation' => 'Newpassword123!',
            ])->assertOk();

        // Only current token should remain
        $this->assertEquals(1, $this->activeUser->tokens()->count());
    }

    public function test_change_password_without_confirmation_fails(): void
    {
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->postJson('/api/v1/profile/change-password', [
            'current_password' => 'senha1234',
            'new_password' => 'Newpassword123!',
        ]);

        $response->assertStatus(422);
    }

    public function test_change_password_with_weak_password_fails(): void
    {
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->postJson('/api/v1/profile/change-password', [
            'current_password' => 'senha1234',
            'new_password' => 'weak',
            'new_password_confirmation' => 'weak',
        ]);

        $response->assertStatus(422);
    }

    public function test_change_password_with_mismatched_confirmation_fails(): void
    {
        Sanctum::actingAs($this->activeUser, ['*']);

        $response = $this->postJson('/api/v1/profile/change-password', [
            'current_password' => 'senha1234',
            'new_password' => 'Newpassword123!',
            'new_password_confirmation' => 'DifferentPassword123!',
        ]);

        $response->assertStatus(422);
    }

    // ══════════════════════════════════════════════
    // ── PASSWORD RESET FLOW
    // ══════════════════════════════════════════════

    public function test_forgot_password_returns_generic_message_for_existing_email(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'active@test.com',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Se o e-mail estiver cadastrado', $response->json('message'));
    }

    public function test_forgot_password_returns_generic_message_for_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', [
            'email' => 'nonexistent@test.com',
        ]);

        // Should NOT reveal if email exists — same generic message
        $response->assertOk();
        $this->assertStringContainsString('Se o e-mail estiver cadastrado', $response->json('message'));
    }

    public function test_forgot_password_without_email_returns_422(): void
    {
        $response = $this->postJson('/api/v1/forgot-password', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_reset_password_with_invalid_token_returns_422(): void
    {
        $response = $this->postJson('/api/v1/reset-password', [
            'token' => 'invalid-token-12345',
            'email' => 'active@test.com',
            'password' => 'Newpassword123!',
            'password_confirmation' => 'Newpassword123!',
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('inv', strtolower($response->json('message')));
    }

    public function test_reset_password_with_valid_token_revokes_all_tokens(): void
    {
        // Create existing tokens
        $this->activeUser->createToken('session-1');
        $this->activeUser->createToken('session-2');
        $this->assertGreaterThanOrEqual(2, $this->activeUser->tokens()->count());

        // Create a real password reset token
        $token = Password::createToken($this->activeUser);

        $response = $this->postJson('/api/v1/reset-password', [
            'token' => $token,
            'email' => 'active@test.com',
            'password' => 'Newpassword123!',
            'password_confirmation' => 'Newpassword123!',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Senha redefinida', $response->json('message'));

        // ALL tokens should be revoked
        $this->assertEquals(0, $this->activeUser->tokens()->count());

        // New password should work
        $this->activeUser->refresh();
        $this->assertTrue(Hash::check('Newpassword123!', $this->activeUser->password));
    }

    // ══════════════════════════════════════════════
    // ── AUDIT LOG VERIFICATION
    // ══════════════════════════════════════════════

    public function test_login_creates_audit_log_entry(): void
    {
        $this->postJson('/api/v1/login', [
            'email' => 'active@test.com',
            'password' => 'senha1234',
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login',
        ]);
    }

    public function test_switch_tenant_creates_audit_log_entry(): void
    {
        $this->activeUser->tenants()->attach($this->otherTenant->id, ['is_default' => false]);
        Sanctum::actingAs($this->activeUser, ['*']);

        $this->postJson('/api/v1/switch-tenant', [
            'tenant_id' => $this->otherTenant->id,
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tenant_switch',
        ]);
    }
}
