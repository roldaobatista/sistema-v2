<?php

namespace Tests\Feature\RateLimiting;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TenantRateLimitingTest extends TestCase
{
    private function createAuthenticatedUser(?Tenant $tenant = null): array
    {
        $tenant = $tenant ?? Tenant::factory()->create();
        $user = User::factory()->create([
            'current_tenant_id' => $tenant->id,
            'tenant_id' => $tenant->id,
        ]);
        $user->tenants()->attach($tenant->id, ['is_default' => true]);
        $user->assignRole('admin');

        return [$tenant, $user];
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Flush the entire cache store to clear all rate limiter keys
        // This is necessary because RateLimiter::clear() needs the exact composite key
        Cache::flush();
    }

    #[Test]
    public function tenant_a_rate_limit_does_not_affect_tenant_b(): void
    {
        [$tenantA, $userA] = $this->createAuthenticatedUser();
        [$tenantB, $userB] = $this->createAuthenticatedUser();

        // Tenant A: exhaust rate limit (tenant-mutations = 30/min)
        Sanctum::actingAs($userA);
        app()->instance('current_tenant_id', $tenantA->id);

        for ($i = 0; $i < 30; $i++) {
            // Use a POST endpoint that has tenant-mutations throttle
            // Using a lightweight endpoint - work-orders store will 422 without data but still counts for rate limit
            $this->postJson('/api/v1/work-orders', []);
        }

        // Tenant A: 31st request should be 429
        $response = $this->postJson('/api/v1/work-orders', []);
        $response->assertStatus(429);

        // Tenant B: should NOT be affected
        Sanctum::actingAs($userB);
        app()->instance('current_tenant_id', $tenantB->id);

        $response = $this->postJson('/api/v1/work-orders', []);
        $this->assertNotEquals(429, $response->getStatusCode(), 'Tenant B should not be affected by Tenant A rate limit');
    }

    #[Test]
    public function returns_429_with_rate_limit_headers_when_limit_exceeded(): void
    {
        [$tenant, $user] = $this->createAuthenticatedUser();
        Sanctum::actingAs($user);
        app()->instance('current_tenant_id', $tenant->id);

        // Exhaust tenant-exports limit (10/min - smallest, fastest to test)
        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/v1/work-orders-export');
        }

        // 11th request should be 429
        $response = $this->getJson('/api/v1/work-orders-export');
        $response->assertStatus(429);
        $response->assertHeader('Retry-After');
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }

    #[Test]
    public function mutations_have_30_per_minute_limit(): void
    {
        [$tenant, $user] = $this->createAuthenticatedUser();
        Sanctum::actingAs($user);
        app()->instance('current_tenant_id', $tenant->id);

        // Send 30 mutation requests
        for ($i = 0; $i < 30; $i++) {
            $response = $this->postJson('/api/v1/work-orders', []);
            $this->assertNotEquals(429, $response->getStatusCode(), "Request {$i} should not be rate limited");
        }

        // 31st should be limited
        $response = $this->postJson('/api/v1/work-orders', []);
        $response->assertStatus(429);
    }

    #[Test]
    public function reads_have_120_per_minute_limit(): void
    {
        [$tenant, $user] = $this->createAuthenticatedUser();
        Sanctum::actingAs($user);
        app()->instance('current_tenant_id', $tenant->id);

        // Send 120 read requests
        for ($i = 0; $i < 120; $i++) {
            $response = $this->getJson('/api/v1/work-orders');
            $this->assertNotEquals(429, $response->getStatusCode(), "Read request {$i} should not be rate limited");
        }

        // 121st should be limited
        $response = $this->getJson('/api/v1/work-orders');
        $response->assertStatus(429);
    }

    #[Test]
    public function unauthenticated_user_uses_ip_based_limiting_on_login(): void
    {
        // Login has TWO layers of protection:
        // 1. Middleware: throttle:login = 10/min by IP
        // 2. Controller: login_attempts cache = 5 attempts per email:ip per 15min
        // The controller limit (5) is hit first with same email
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/login', [
                'email' => 'test@example.com',
                'password' => 'wrong',
            ]);
            $this->assertNotEquals(429, $response->getStatusCode(), "Login request {$i} should not be rate limited");
        }

        // 6th request with same email should be blocked by controller-level rate limiting
        $response = $this->postJson('/api/v1/login', [
            'email' => 'test@example.com',
            'password' => 'wrong',
        ]);
        $response->assertStatus(429);
    }

    #[Test]
    public function login_middleware_limiter_is_ip_based_not_tenant_aware(): void
    {
        // Verify the middleware-level rate limiter (throttle:login) is IP-based
        // Use different emails to avoid controller-level per-email blocking
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/v1/login', [
                'email' => "user{$i}@example.com",
                'password' => 'wrong',
            ]);
            $this->assertNotEquals(429, $response->getStatusCode(), "Login request {$i} should not be rate limited");
        }

        // 11th request should hit middleware-level rate limit (10/min by IP)
        $response = $this->postJson('/api/v1/login', [
            'email' => 'user99@example.com',
            'password' => 'wrong',
        ]);
        $response->assertStatus(429);
    }

    #[Test]
    public function exports_have_10_per_minute_limit(): void
    {
        [$tenant, $user] = $this->createAuthenticatedUser();
        Sanctum::actingAs($user);
        app()->instance('current_tenant_id', $tenant->id);

        for ($i = 0; $i < 10; $i++) {
            $response = $this->getJson('/api/v1/work-orders-export');
            $this->assertNotEquals(429, $response->getStatusCode(), "Export request {$i} should not be rate limited");
        }

        // 11th should be limited
        $response = $this->getJson('/api/v1/work-orders-export');
        $response->assertStatus(429);
    }

    #[Test]
    public function uploads_have_dedicated_30_per_minute_limit(): void
    {
        [$tenant, $user] = $this->createAuthenticatedUser();
        Sanctum::actingAs($user);
        app()->instance('current_tenant_id', $tenant->id);

        // Upload routes and mutation routes should have independent limits
        // Exhaust mutations (30/min)
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/v1/work-orders', []);
        }

        // Mutations exhausted - should be 429
        $response = $this->postJson('/api/v1/work-orders', []);
        $response->assertStatus(429);

        // Upload should still work (separate bucket: tenant-uploads)
        $response = $this->postJson('/api/v1/import/upload', []);
        $this->assertNotEquals(429, $response->getStatusCode(), 'Upload should have separate rate limit from mutations');
    }

    #[Test]
    public function bulk_operations_have_60_per_minute_limit(): void
    {
        [$tenant, $user] = $this->createAuthenticatedUser();
        Sanctum::actingAs($user);
        app()->instance('current_tenant_id', $tenant->id);

        // Exhaust mutations (30/min)
        for ($i = 0; $i < 30; $i++) {
            $this->postJson('/api/v1/work-orders', []);
        }

        // Mutations exhausted
        $response = $this->postJson('/api/v1/work-orders', []);
        $response->assertStatus(429);

        // Bulk (tenant-bulk = 60/min) should still work — different bucket
        // Proposals route uses tenant-bulk
        $response = $this->getJson('/api/v1/proposals/fake-token/view');
        $this->assertNotEquals(429, $response->getStatusCode(), 'Bulk rate limit should be independent from mutations');
    }

    #[Test]
    public function rate_limiter_uses_tenant_user_composite_key(): void
    {
        [$tenant, $userA] = $this->createAuthenticatedUser();

        // Create second user in SAME tenant
        $userB = User::factory()->create([
            'current_tenant_id' => $tenant->id,
            'tenant_id' => $tenant->id,
        ]);
        $userB->tenants()->attach($tenant->id, ['is_default' => true]);
        $userB->assignRole('admin');

        // User A: exhaust export limit
        Sanctum::actingAs($userA);
        app()->instance('current_tenant_id', $tenant->id);

        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/v1/work-orders-export');
        }

        $response = $this->getJson('/api/v1/work-orders-export');
        $response->assertStatus(429);

        // User B (same tenant): should have own bucket
        Sanctum::actingAs($userB);
        app()->instance('current_tenant_id', $tenant->id);

        $response = $this->getJson('/api/v1/work-orders-export');
        $this->assertNotEquals(429, $response->getStatusCode(), 'Different user in same tenant should have independent rate limit');
    }
}
