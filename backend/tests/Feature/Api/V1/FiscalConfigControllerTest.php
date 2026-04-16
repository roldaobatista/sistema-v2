<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Fiscal\CertificateService;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FiscalConfigControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_show_returns_fiscal_config(): void
    {
        $response = $this->getJson('/api/v1/fiscal/config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'fiscal_regime',
                    'cnae_code',
                    'state_registration',
                    'city_registration',
                    'fiscal_nfse_token',
                    'fiscal_nfse_city',
                    'fiscal_environment',
                    'has_certificate',
                    'certificate_expires_at',
                ],
                'meta' => [
                    'fiscal_regimes',
                    'nfse_cities',
                    'environments',
                ],
            ]);
    }

    public function test_show_includes_meta_regimes(): void
    {
        $response = $this->getJson('/api/v1/fiscal/config');

        $response->assertStatus(200);
        $meta = $response->json('meta');
        $this->assertArrayHasKey('fiscal_regimes', $meta);
        $this->assertCount(4, $meta['fiscal_regimes']);
    }

    public function test_update_fiscal_config(): void
    {
        $response = $this->putJson('/api/v1/fiscal/config', [
            'fiscal_regime' => 1,
            'cnae_code' => '4520-0/01',
            'fiscal_environment' => 'homologation',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Configuração fiscal atualizada com sucesso.');
    }

    public function test_update_fiscal_config_validation(): void
    {
        $response = $this->putJson('/api/v1/fiscal/config', [
            'fiscal_regime' => 99, // invalid
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fiscal_regime']);
    }

    public function test_update_fiscal_environment_validation(): void
    {
        $response = $this->putJson('/api/v1/fiscal/config', [
            'fiscal_environment' => 'invalid_env',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fiscal_environment']);
    }

    public function test_update_fiscal_nfse_city_validation(): void
    {
        $response = $this->putJson('/api/v1/fiscal/config', [
            'fiscal_nfse_city' => 'sao_paulo',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fiscal_nfse_city']);
    }

    public function test_cfop_options_returns_list(): void
    {
        $response = $this->getJson('/api/v1/fiscal/config/cfop-options');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertArrayHasKey('code', $data[0]);
        $this->assertArrayHasKey('description', $data[0]);
    }

    public function test_csosn_options_returns_list(): void
    {
        $response = $this->getJson('/api/v1/fiscal/config/csosn-options');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    public function test_iss_exigibilidade_options_returns_list(): void
    {
        $response = $this->getJson('/api/v1/fiscal/config/iss-exigibilidade-options');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('1', $data[0]['code']);
    }

    public function test_lc116_options_returns_list(): void
    {
        $response = $this->getJson('/api/v1/fiscal/config/lc116-options');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertNotEmpty($data);
    }

    public function test_certificate_status_returns_data(): void
    {
        $mock = $this->mock(CertificateService::class);
        $mock->shouldReceive('status')
            ->once()
            ->andReturn([
                'has_certificate' => false,
                'expires_at' => null,
                'days_to_expire' => null,
            ]);

        $response = $this->getJson('/api/v1/fiscal/config/certificate/status');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'has_certificate',
                    'expires_at',
                    'days_to_expire',
                ],
            ]);
    }

    public function test_remove_certificate_calls_service(): void
    {
        $mock = $this->mock(CertificateService::class);
        $mock->shouldReceive('remove')
            ->once();

        $response = $this->deleteJson('/api/v1/fiscal/config/certificate');

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Certificado removido com sucesso.');
    }

    public function test_upload_certificate_validates_file(): void
    {
        $response = $this->postJson('/api/v1/fiscal/config/certificate', [
            'password' => 'test123',
            // no file
        ]);

        $response->assertStatus(422);
    }
}
