<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Testes profundos do EnsureTenantScope middleware real:
 * Cobre autenticação, tenant selection, tenant access, inactive tenant,
 * me/my-tenants bypass, app binding.
 */
class EnsureTenantScopeTest extends TestCase
{
    private EnsureTenantScope $middleware;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->middleware = new EnsureTenantScope;
        $this->tenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
    }

    private function createRequest(string $uri = '/api/v1/work-orders', ?User $user = null): Request
    {
        $request = Request::create($uri, 'GET');
        if ($user) {
            $request->setUserResolver(fn () => $user);
        }

        return $request;
    }

    private function passThrough(Request $request): Response
    {
        return $this->middleware->handle($request, fn ($r) => response()->json(['ok' => true]));
    }

    // ── Unauthenticated ──

    public function test_returns_401_when_unauthenticated(): void
    {
        $request = $this->createRequest();
        $response = $this->passThrough($request);
        $this->assertEquals(401, $response->getStatusCode());
    }

    // ── No tenant selected (tenant_id = 0) ──

    public function test_returns_403_when_no_tenant_selected(): void
    {
        $user = User::factory()->create([
            'tenant_id' => 0,
            'current_tenant_id' => 0,
        ]);
        $request = $this->createRequest('/api/v1/work-orders', $user);
        $response = $this->passThrough($request);
        $this->assertEquals(403, $response->getStatusCode());
    }

    // ── me/my-tenants bypass ──

    public function test_me_endpoint_bypasses_tenant_check(): void
    {
        $user = User::factory()->create([
            'tenant_id' => 0,
            'current_tenant_id' => 0,
        ]);
        $request = $this->createRequest('/api/v1/me', $user);
        $response = $this->passThrough($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_legacy_auth_user_endpoint_bypasses_tenant_check(): void
    {
        $user = User::factory()->create([
            'tenant_id' => 0,
            'current_tenant_id' => 0,
        ]);
        $request = $this->createRequest('/api/v1/auth/user', $user);
        $response = $this->passThrough($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_my_tenants_endpoint_bypasses_tenant_check(): void
    {
        $user = User::factory()->create([
            'tenant_id' => 0,
            'current_tenant_id' => 0,
        ]);
        $request = $this->createRequest('/api/v1/my-tenants', $user);
        $response = $this->passThrough($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // ── User without access to tenant ──

    public function test_returns_403_when_user_has_no_tenant_access(): void
    {
        $otherTenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        // User NOT attached to otherTenant
        $request = $this->createRequest('/api/v1/work-orders', $user);
        $response = $this->passThrough($request);
        $this->assertEquals(403, $response->getStatusCode());
    }

    // ── Valid tenant access ──

    public function test_passes_with_valid_tenant(): void
    {
        $request = $this->createRequest('/api/v1/work-orders', $this->user);
        $response = $this->passThrough($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_binds_current_tenant_id(): void
    {
        $request = $this->createRequest('/api/v1/work-orders', $this->user);
        $this->passThrough($request);
        $this->assertEquals($this->tenant->id, app('current_tenant_id'));
    }

    public function test_does_not_merge_tenant_id_into_request_body(): void
    {
        // sec-10 / CLAUDE.md Lei 4: middleware não injeta tenant_id no body.
        // Contexto de tenant fica apenas em `app('current_tenant_id')`.
        $request = $this->createRequest('/api/v1/work-orders', $this->user);
        $this->passThrough($request);
        $this->assertNull($request->get('tenant_id'), 'Middleware não deve mergear tenant_id no body (sec-10 / Lei 4)');
        $this->assertEquals($this->tenant->id, app('current_tenant_id'), 'Binding legítimo de tenant é no container');
    }

    // ── Inactive tenant ──

    public function test_returns_403_for_inactive_tenant(): void
    {
        $inactiveTenant = Tenant::factory()->create(['status' => Tenant::STATUS_INACTIVE]);
        $user = User::factory()->create([
            'tenant_id' => $inactiveTenant->id,
            'current_tenant_id' => $inactiveTenant->id,
        ]);
        $user->tenants()->attach($inactiveTenant->id, ['is_default' => true]);

        Cache::forget("tenant_status_{$inactiveTenant->id}");

        $request = $this->createRequest('/api/v1/work-orders', $user);
        $response = $this->passThrough($request);
        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_inactive_tenant_allows_me_endpoint(): void
    {
        $inactiveTenant = Tenant::factory()->create(['status' => Tenant::STATUS_INACTIVE]);
        $user = User::factory()->create([
            'tenant_id' => $inactiveTenant->id,
            'current_tenant_id' => $inactiveTenant->id,
        ]);
        $user->tenants()->attach($inactiveTenant->id, ['is_default' => true]);

        Cache::forget("tenant_status_{$inactiveTenant->id}");

        $request = $this->createRequest('/api/v1/me', $user);
        $response = $this->passThrough($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_inactive_tenant_allows_logout(): void
    {
        $inactiveTenant = Tenant::factory()->create(['status' => Tenant::STATUS_INACTIVE]);
        $user = User::factory()->create([
            'tenant_id' => $inactiveTenant->id,
            'current_tenant_id' => $inactiveTenant->id,
        ]);
        $user->tenants()->attach($inactiveTenant->id, ['is_default' => true]);

        Cache::forget("tenant_status_{$inactiveTenant->id}");

        $request = $this->createRequest('/api/v1/logout', $user);
        $response = $this->passThrough($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_inactive_tenant_allows_legacy_auth_user_endpoint(): void
    {
        $inactiveTenant = Tenant::factory()->create(['status' => Tenant::STATUS_INACTIVE]);
        $user = User::factory()->create([
            'tenant_id' => $inactiveTenant->id,
            'current_tenant_id' => $inactiveTenant->id,
        ]);
        $user->tenants()->attach($inactiveTenant->id, ['is_default' => true]);

        Cache::forget("tenant_status_{$inactiveTenant->id}");

        $request = $this->createRequest('/api/v1/auth/user', $user);
        $response = $this->passThrough($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_inactive_tenant_allows_legacy_auth_logout_endpoint(): void
    {
        $inactiveTenant = Tenant::factory()->create(['status' => Tenant::STATUS_INACTIVE]);
        $user = User::factory()->create([
            'tenant_id' => $inactiveTenant->id,
            'current_tenant_id' => $inactiveTenant->id,
        ]);
        $user->tenants()->attach($inactiveTenant->id, ['is_default' => true]);

        Cache::forget("tenant_status_{$inactiveTenant->id}");

        $request = $this->createRequest('/api/v1/auth/logout', $user);
        $response = $this->passThrough($request);
        $this->assertEquals(200, $response->getStatusCode());
    }

    // ── Tenant not found ──

    public function test_returns_403_when_tenant_not_found(): void
    {
        $user = User::factory()->create([
            'tenant_id' => 999999,
            'current_tenant_id' => 999999,
        ]);

        $request = $this->createRequest('/api/v1/work-orders', $user);
        $response = $this->passThrough($request);
        $this->assertEquals(403, $response->getStatusCode());
    }
}
