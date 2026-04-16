<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InmetroService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InmetroControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_dashboard_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/inmetro/dashboard');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_owners_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/inmetro/owners');

        $response->assertOk();
    }

    public function test_instruments_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/inmetro/instruments');

        $response->assertOk();
    }

    public function test_leads_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/inmetro/leads');

        $response->assertOk();
    }

    public function test_leads_rejects_invalid_filter_types(): void
    {
        $response = $this->getJson('/api/v1/inmetro/leads?per_page=abc&only_leads=maybe');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['per_page', 'only_leads']);
    }

    public function test_service_endpoints_do_not_forward_tenant_id_from_payload(): void
    {
        $otherTenant = Tenant::factory()->create();

        $mock = $this->mock(InmetroService::class);
        $mock->shouldReceive('geocodeLocations')
            ->once()
            ->withArgs(function (array $data, User $user, int $tenantId) use ($otherTenant): bool {
                return $tenantId === $this->tenant->id
                    && $user->is($this->user)
                    && ($data['limit'] ?? null) === 10
                    && ! array_key_exists('tenant_id', $data)
                    && $otherTenant->id !== $tenantId;
            })
            ->andReturn(response()->json(['data' => ['ok' => true]]));

        $response = $this->postJson('/api/v1/inmetro/geocode', [
            'tenant_id' => $otherTenant->id,
            'limit' => 10,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.ok', true);
    }

    public function test_competitors_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/inmetro/competitors');

        $response->assertOk();
    }

    public function test_instrument_types_returns_list(): void
    {
        $response = $this->getJson('/api/v1/inmetro/instrument-types');

        $response->assertOk();
    }

    public function test_available_ufs_returns_list(): void
    {
        $response = $this->getJson('/api/v1/inmetro/available-ufs');

        $response->assertOk();
    }

    public function test_municipalities_returns_ibge_payload(): void
    {
        Cache::forget('ibge_mt_municipalities');
        Http::fake([
            'servicodados.ibge.gov.br/*' => Http::response([
                ['nome' => 'Cuiaba'],
                ['nome' => 'Rondonopolis'],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/inmetro/municipalities');

        $response->assertOk()
            ->assertJsonPath('data.0', 'Cuiaba')
            ->assertJsonPath('data.1', 'Rondonopolis');
    }

    public function test_available_datasets_returns_dados_gov_payload(): void
    {
        Cache::forget('dadosgov:datasets:inmetro');
        Http::fake([
            'dados.gov.br/*' => Http::response([
                'registros' => [
                    [
                        'id' => 123,
                        'titulo' => 'Instrumentos INMETRO',
                        'descricao' => 'Dados publicos de metrologia legal',
                        'organizacao' => ['nome' => 'INMETRO'],
                        'recursos' => [['id' => 1]],
                        'dataUltimaAtualizacao' => '2026-01-01',
                    ],
                    [
                        'id' => 456,
                        'titulo' => 'Outro dataset',
                        'descricao' => 'Fora do filtro',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/v1/inmetro/available-datasets');

        $response->assertOk()
            ->assertJsonPath('data.datasets.0.id', 123)
            ->assertJsonPath('data.datasets.0.title', 'Instrumentos INMETRO');
    }

    public function test_store_owner_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/inmetro/owners', []);

        $response->assertStatus(422);
    }

    public function test_store_competitor_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/inmetro/competitors', []);

        $response->assertStatus(422);
    }
}
