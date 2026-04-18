<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
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

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_only_current_tenant_logs(): void
    {
        // 3 logs do tenant atual
        AuditLog::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        // 5 logs de OUTRO tenant (nao podem vazar)
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        AuditLog::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/audit-logs');

        $response->assertOk()
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertIsArray($data);

        // Verifica que nenhum log de outro tenant vazou
        $tenantIds = collect($data)->pluck('tenant_id')->unique()->all();
        $this->assertEquals(
            [$this->tenant->id],
            array_values($tenantIds),
            'Logs de outros tenants vazaram na listagem — isolamento quebrado'
        );
    }

    public function test_index_filter_user_id_does_not_leak_other_tenant_users(): void
    {
        // AUDIT FINDING P0: ListAuditLogRequest.user_id = 'exists:users,id' sem tenant scope
        // -> User enumeration: atacante pode iterar user_ids e ver quais existem globalmente.
        //
        // Comportamento esperado CORRETO: filtro por user_id de OUTRO tenant deve retornar
        // lista vazia (nao vazar existencia nem conteudo).

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        AuditLog::factory()->count(10)->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/audit-logs?user_id='.$otherUser->id);

        // Contrato fixado: global scope BelongsToTenant filtra; filtro por user
        // de outro tenant retorna 200 com lista vazia (nao vaza existencia nem dados).
        $response->assertStatus(200);
        $data = $response->json('data') ?? [];
        $this->assertCount(0, $data, 'Logs de user de outro tenant vazaram via filtro user_id');
    }

    public function test_actions_endpoint_returns_distinct_actions_of_tenant(): void
    {
        // Tenant atual: created + updated + deleted
        AuditLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'created',
        ]);
        AuditLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'updated',
        ]);
        AuditLog::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'deleted',
        ]);

        // Tenant OUTRO: tenant_switch (enum valido, mas exclusivo dele neste teste)
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        AuditLog::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
            'action' => 'tenant_switch',
        ]);

        $response = $this->getJson('/api/v1/audit-logs/actions');

        $response->assertOk()->assertJsonStructure(['data']);

        $actions = $response->json('data');
        $this->assertIsArray($actions);
        $this->assertContains('created', $actions);
        $this->assertContains('updated', $actions);
        $this->assertContains('deleted', $actions);
        $this->assertNotContains(
            'tenant_switch',
            $actions,
            'Endpoint actions vazou action exclusiva de outro tenant — tenant isolation quebrado'
        );
    }

    public function test_show_returns_404_for_cross_tenant_log(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);
        $foreignLog = AuditLog::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/audit-logs/'.$foreignLog->id);

        // Deve retornar 404 (e nao 200/403) para nao vazar existencia
        $response->assertStatus(404);
    }

    public function test_index_rejects_invalid_date_range(): void
    {
        $response = $this->getJson('/api/v1/audit-logs?'.http_build_query([
            'from' => '2026-04-30',
            'to' => '2026-04-01',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to']);
    }

    public function test_index_validates_per_page_ceiling(): void
    {
        $response = $this->getJson('/api/v1/audit-logs?per_page=9999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }
}
