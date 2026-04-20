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

    // -----------------------------------------------------------------
    // sec-csp-unsafe-inline-eval — Camada 1 r4 Batch B
    // Em produção CSP NÃO pode conter 'unsafe-eval' nem 'unsafe-inline'
    // em script-src. Style-src pode manter 'unsafe-inline' com exceção
    // documentada (Tailwind/Radix geram styles inline). Ver
    // TECHNICAL-DECISIONS.md §14.22.
    // -----------------------------------------------------------------

    public function test_csp_has_script_src_directive(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/user');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp, 'CSP header must be present');
        $this->assertStringContainsString('script-src', $csp);
    }

    public function test_csp_in_production_has_no_unsafe_eval_in_script_src(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->actingAs($this->user)->getJson('/api/user');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        // Isolar a directiva script-src (pode haver outras com 'unsafe-inline' permitido)
        $scriptSrc = $this->extractDirective($csp, 'script-src');
        $this->assertNotNull($scriptSrc, 'script-src directive must exist');
        $this->assertStringNotContainsString("'unsafe-eval'", $scriptSrc);
    }

    public function test_csp_in_production_has_no_unsafe_inline_in_script_src(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $response = $this->actingAs($this->user)->getJson('/api/user');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $scriptSrc = $this->extractDirective($csp, 'script-src');
        $this->assertNotNull($scriptSrc);
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);
    }

    public function test_csp_in_non_production_keeps_relaxed_directives_for_vite_hmr(): void
    {
        // Em dev/test, Vite HMR usa eval() — manter relaxado
        $response = $this->actingAs($this->user)->getJson('/api/user');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $scriptSrc = $this->extractDirective($csp, 'script-src');
        $this->assertNotNull($scriptSrc);
        // Ambiente de teste é 'testing' (não production) -> relaxed
        $this->assertStringContainsString("'unsafe-eval'", $scriptSrc);
    }

    private function extractDirective(string $csp, string $directive): ?string
    {
        foreach (explode(';', $csp) as $part) {
            $part = trim($part);
            if (str_starts_with($part, $directive.' ') || $part === $directive) {
                return $part;
            }
        }

        return null;
    }
}
