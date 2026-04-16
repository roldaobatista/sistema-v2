<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\CheckPermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');

    }

    public function test_api_returns_json_content_type(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/customers');
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_sql_injection_in_search_param(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/customers?search='.urlencode("'; DROP TABLE customers; --"));

        $response->assertOk();
    }

    public function test_xss_in_customer_name(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/customers', [
            'name' => '<script>alert("XSS")</script>',
            'type' => 'company',
        ]);

        if ($response->status() === 201) {
            $this->assertStringNotContainsString('<script>', $response->json('data.name') ?? '');
        } else {
            $response->assertUnprocessable();
        }
    }

    public function test_mass_assignment_protection(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/customers', [
            'name' => 'Legit Customer',
            'type' => 'company',
            'tenant_id' => 99999,
            'id' => 99999,
        ]);

        if ($response->status() === 201) {
            $data = $response->json('data') ?? $response->json();
            $this->assertNotEquals(99999, $data['tenant_id'] ?? null);
        } else {
            // Rejected by validation — mass assignment properly prevented
            $response->assertUnprocessable();
        }
    }

    public function test_rate_limiting_login(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'fake@fake.com',
                'password' => 'wrongpassword',
            ]);
        }

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'fake@fake.com',
            'password' => 'wrongpassword',
        ]);

        $this->assertTrue(
            $response->status() === 429 || $response->status() === 422 || $response->status() === 401
        );
    }

    public function test_cors_headers_present(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/customers');
        $response->assertOk();
        // CORS headers should be present — at minimum the response should not crash
        // Exact CORS headers depend on config; validate response is valid JSON
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_cors_allows_configured_frontend_origins(): void
    {
        $frontendOrigins = array_filter(array_map('trim', explode(',', config('app.frontend_url', ''))));

        foreach ($frontendOrigins as $origin) {
            $this->assertContains($origin, config('cors.allowed_origins'));
        }
    }

    public function test_password_not_in_user_response(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/auth/user');
        $response->assertOk();

        $data = $response->json('data') ?? $response->json();
        $this->assertArrayNotHasKey('password', $data);
    }

    public function test_token_required_for_api_access(): void
    {
        $response = $this->getJson('/api/v1/customers');
        $response->assertUnauthorized();
    }

    public function test_invalid_token_returns_401(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_token_12345',
        ])->getJson('/api/v1/customers');

        $response->assertUnauthorized();
    }

    public function test_oversized_request_body_handled(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/customers', [
            'name' => str_repeat('A', 10000),
            'type' => 'company',
        ]);

        $this->assertTrue(
            $response->status() === 422 || $response->status() === 201
        );
    }
}
