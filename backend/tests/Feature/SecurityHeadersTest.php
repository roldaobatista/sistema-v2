<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_response_includes_x_content_type_options_header(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/user');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_response_includes_x_frame_options_header(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/user');

        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_response_includes_referrer_policy_header(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/user');

        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_response_includes_permissions_policy_header(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/user');

        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');
    }

    public function test_response_includes_x_xss_protection_header(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/user');

        $response->assertHeader('X-XSS-Protection', '0');
    }

    public function test_unauthenticated_request_also_gets_security_headers(): void
    {
        $response = $this->getJson('/up');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_health_endpoint_gets_security_headers(): void
    {
        $response = $this->get('/up');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_hsts_header_not_present_on_insecure_request(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/user');

        $response->assertHeaderMissing('Strict-Transport-Security');
    }
}
