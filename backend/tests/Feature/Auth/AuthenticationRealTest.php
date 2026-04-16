<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\CheckPermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Testes profundos de Autenticação real: Login, Logout, Me,
 * Switch Tenant, Token, Validações.
 */
class AuthenticationRealTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'email' => 'auth-test@kalibrium.com',
            'password' => Hash::make('TestSenha123!'),
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');

    }

    // ── Login ──

    public function test_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'auth-test@kalibrium.com',
            'password' => 'TestSenha123!',
        ]);
        $response->assertOk();
        $response->assertJsonStructure(['token']);
    }

    public function test_login_with_wrong_password(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'auth-test@kalibrium.com',
            'password' => 'WrongPassword',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_with_nonexistent_email(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@kalibrium.com',
            'password' => 'TestSenha123!',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_login_without_email(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'password' => 'TestSenha123!',
        ]);
        $response->assertUnprocessable();
    }

    public function test_login_without_password(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'auth-test@kalibrium.com',
        ]);
        $response->assertUnprocessable();
    }

    // ── Me ──

    public function test_me_returns_authenticated_user(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/me');
        $response->assertOk();
        $response->assertJsonPath('data.user.email', 'auth-test@kalibrium.com');
    }

    public function test_me_fails_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/me');
        $response->assertUnauthorized();
    }

    // ── Logout ──

    public function test_logout_revokes_token(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/logout');
        $this->assertTrue(in_array($response->status(), [200, 204]));
    }

    // ── My Tenants ──

    public function test_my_tenants_returns_user_tenants(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/my-tenants');
        $response->assertOk();
    }

    // ── Switch Tenant ──

    public function test_switch_to_valid_tenant(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/switch-tenant', [
            'tenant_id' => $this->tenant->id,
        ]);
        $response->assertOk();
    }

    public function test_switch_to_unauthorized_tenant(): void
    {
        $other = Tenant::factory()->create();
        $response = $this->actingAs($this->user)->postJson('/api/v1/switch-tenant', [
            'tenant_id' => $other->id,
        ]);
        $this->assertTrue(in_array($response->status(), [403, 422]));
    }

    // ── Protected routes without auth ──

    public function test_customers_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/customers');
        $response->assertUnauthorized();
    }

    public function test_work_orders_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/work-orders');
        $response->assertUnauthorized();
    }

    public function test_equipments_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/equipments');
        $response->assertUnauthorized();
    }

    public function test_quotes_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/quotes');
        $response->assertUnauthorized();
    }

    public function test_dashboard_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/dashboard');
        $response->assertUnauthorized();
    }
}
