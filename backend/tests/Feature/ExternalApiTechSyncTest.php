<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * External APIs (CEP, CNPJ, holidays, banks, states)
 * + TechSync (pull, batch push).
 */
class ExternalApiTechSyncTest extends TestCase
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
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── EXTERNAL APIs ──

    public function test_cep_lookup(): void
    {
        Http::fake([
            'viacep.com.br/*' => Http::response([
                'cep' => '01001-000',
                'logradouro' => 'Praça da Sé',
                'complemento' => 'lado ímpar',
                'bairro' => 'Sé',
                'localidade' => 'São Paulo',
                'uf' => 'SP',
                'ibge' => '3550308',
            ]),
        ]);

        $response = $this->getJson('/api/v1/external/cep/01001000');
        $response->assertOk();
    }

    public function test_cnpj_lookup(): void
    {
        Http::fake([
            'brasilapi.com.br/*' => Http::response([
                'cnpj' => '11222333000181',
                'razao_social' => 'EMPRESA TESTE LTDA',
                'nome_fantasia' => 'TESTE',
                'email' => 'contato@teste.com',
                'ddd_telefone_1' => '1199999999',
                'cep' => '01001000',
                'descricao_tipo_de_logradouro' => 'RUA',
                'logradouro' => 'TESTE',
                'numero' => '100',
                'complemento' => '',
                'bairro' => 'CENTRO',
                'municipio' => 'SAO PAULO',
                'uf' => 'SP',
                'descricao_situacao_cadastral' => 'ATIVA',
                'cnae_fiscal' => 4712100,
                'cnae_fiscal_descricao' => 'Comércio varejista',
                'porte' => 'MICRO EMPRESA',
                'data_inicio_atividade' => '2020-01-15',
            ]),
        ]);

        $response = $this->getJson('/api/v1/external/cnpj/11222333000181');
        $response->assertOk();
    }

    public function test_holidays_for_year(): void
    {
        $response = $this->getJson('/api/v1/external/holidays/2025');
        $response->assertOk();
    }

    public function test_banks_list(): void
    {
        $response = $this->getJson('/api/v1/external/banks');
        $response->assertOk();
    }

    public function test_states_list(): void
    {
        $response = $this->getJson('/api/v1/external/states');
        $response->assertOk();
    }

    public function test_cities_by_state(): void
    {
        $response = $this->getJson('/api/v1/external/states/SP/cities');
        $response->assertOk();
    }

    // ── TECH SYNC ──

    public function test_tech_sync_pull(): void
    {
        $response = $this->getJson('/api/v1/tech/sync');
        $response->assertOk();
    }

    public function test_tech_sync_batch_push(): void
    {
        $response = $this->postJson('/api/v1/tech/sync/batch', [
            'mutations' => [],
        ]);
        $response->assertOk();
    }

    public function test_tech_sync_batch_push_with_conflict(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->user->id,
            'status' => 'pending',
            // Define a data de atualização do servidor NO FUTURO (ou recente)
            'updated_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/tech/sync/batch', [
            'mutations' => [
                [
                    'id' => 'temp-123',
                    'type' => 'status_change',
                    'data' => [
                        'work_order_id' => $workOrder->id,
                        'to_status' => 'in_progress',
                        // O cliente tenta sobrescrever usando uma data BASE defasada no tempo
                        'updated_at' => now()->subMinutes(10)->toIso8601String(),
                    ],
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'processed',
                'conflicts',
                'errors',
            ],
        ]);

        // Esperamos que haja retorno no array de conflicts
        $this->assertNotEmpty($response->json('data.conflicts'), 'Deveria ter gerado um conflito na API pois a data do PWA era inferior.');

        // Confirma que a OS não foi sobrescrita
        $this->assertEquals('pending', $workOrder->refresh()->status);
    }
}
