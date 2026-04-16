<?php

namespace Tests\Feature\Api\V1\Quality;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\NonConformity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NonConformityControllerTest extends TestCase
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

    private function createNC(array $overrides = []): NonConformity
    {
        return NonConformity::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'nc_number' => 'NC-'.str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT),
            'title' => 'Desvio de processo detectado',
            'description' => 'Descrição detalhada da não conformidade encontrada.',
            'source' => 'process_deviation',
            'severity' => 'major',
            'status' => 'open',
            'reported_by' => $this->user->id,
        ], $overrides));
    }

    // ─── INDEX ────────────────────────────────────────────────────────

    public function test_index_returns_paginated_non_conformities(): void
    {
        $this->createNC(['title' => 'NC Alpha']);
        $this->createNC(['title' => 'NC Beta']);

        $response = $this->getJson('/api/v1/non-conformities');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_index_filters_by_search(): void
    {
        $this->createNC(['title' => 'Falha no processo de soldagem']);
        $this->createNC(['title' => 'Desvio dimensional']);

        $response = $this->getJson('/api/v1/non-conformities?search=soldagem');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('soldagem', $data[0]['title']);
    }

    public function test_index_filters_by_status(): void
    {
        $this->createNC(['status' => 'open']);
        $this->createNC(['status' => 'closed', 'closed_at' => now()]);

        $response = $this->getJson('/api/v1/non-conformities?status=closed');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('closed', $data[0]['status']);
    }

    public function test_index_filters_by_source(): void
    {
        $this->createNC(['source' => 'audit']);
        $this->createNC(['source' => 'customer_complaint']);

        $response = $this->getJson('/api/v1/non-conformities?source=audit');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('audit', $data[0]['source']);
    }

    public function test_index_filters_by_severity(): void
    {
        $this->createNC(['severity' => 'minor']);
        $this->createNC(['severity' => 'critical']);

        $response = $this->getJson('/api/v1/non-conformities?severity=critical');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('critical', $data[0]['severity']);
    }

    public function test_index_respects_tenant_isolation(): void
    {
        $this->createNC(['title' => 'My NC']);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        NonConformity::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'nc_number' => 'NC-OTHER',
            'title' => 'Other Tenant NC',
            'description' => 'Nao deve aparecer',
            'source' => 'audit',
            'severity' => 'minor',
            'status' => 'open',
            'reported_by' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/non-conformities');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('My NC', $titles);
        $this->assertNotContains('Other Tenant NC', $titles);
    }

    // ─── STORE ────────────────────────────────────────────────────────

    public function test_store_creates_non_conformity(): void
    {
        $response = $this->postJson('/api/v1/non-conformities', [
            'title' => 'Peça fora de especificação',
            'description' => 'Encontrada peça com dimensão acima da tolerância.',
            'source' => 'process_deviation',
            'severity' => 'major',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Peça fora de especificação')
            ->assertJsonPath('data.source', 'process_deviation')
            ->assertJsonPath('data.severity', 'major')
            ->assertJsonPath('data.status', 'open');

        $ncNumber = $response->json('data.nc_number');
        $this->assertStringStartsWith('NC-', $ncNumber);
    }

    public function test_store_creates_nc_with_assigned_to(): void
    {
        $assignee = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/v1/non-conformities', [
            'title' => 'NC com responsável',
            'description' => 'Descrição da NC.',
            'source' => 'customer_complaint',
            'severity' => 'critical',
            'assigned_to' => $assignee->id,
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.assigned_to', $assignee->id);
    }

    public function test_store_validation_requires_fields(): void
    {
        $response = $this->postJson('/api/v1/non-conformities', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'description', 'source', 'severity']);
    }

    public function test_store_validation_rejects_invalid_source(): void
    {
        $response = $this->postJson('/api/v1/non-conformities', [
            'title' => 'NC Test',
            'description' => 'Description',
            'source' => 'invalid_source',
            'severity' => 'minor',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['source']);
    }

    public function test_store_validation_rejects_invalid_severity(): void
    {
        $response = $this->postJson('/api/v1/non-conformities', [
            'title' => 'NC Test',
            'description' => 'Description',
            'source' => 'audit',
            'severity' => 'extreme',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['severity']);
    }

    // ─── SHOW ─────────────────────────────────────────────────────────

    public function test_show_returns_nc_with_relations(): void
    {
        $nc = $this->createNC(['title' => 'NC Detalhada']);

        $response = $this->getJson("/api/v1/non-conformities/{$nc->id}");

        $response->assertOk()
            ->assertJsonPath('data.title', 'NC Detalhada')
            ->assertJsonPath('data.reporter.id', $this->user->id);
    }

    public function test_show_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $nc = NonConformity::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'nc_number' => 'NC-ALIEN',
            'title' => 'Alien NC',
            'description' => 'Not my tenant',
            'source' => 'audit',
            'severity' => 'minor',
            'status' => 'open',
            'reported_by' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/v1/non-conformities/{$nc->id}");

        $response->assertStatus(404);
    }

    // ─── UPDATE ───────────────────────────────────────────────────────

    public function test_update_modifies_non_conformity(): void
    {
        $nc = $this->createNC(['title' => 'Original', 'status' => 'open']);

        $response = $this->putJson("/api/v1/non-conformities/{$nc->id}", [
            'title' => 'Updated Title',
            'status' => 'investigating',
            'root_cause' => 'Falta de calibração do instrumento',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.status', 'investigating')
            ->assertJsonPath('data.root_cause', 'Falta de calibração do instrumento');
    }

    public function test_update_auto_sets_closed_at_on_close(): void
    {
        $nc = $this->createNC(['status' => 'corrective_action']);

        $response = $this->putJson("/api/v1/non-conformities/{$nc->id}", [
            'status' => 'closed',
            'corrective_action' => 'Recalibração realizada',
            'verification_notes' => 'Verificação OK',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'closed');
        $this->assertNotNull($response->json('data.closed_at'));
    }

    public function test_update_validates_status_values(): void
    {
        $nc = $this->createNC();

        $response = $this->putJson("/api/v1/non-conformities/{$nc->id}", [
            'status' => 'nonexistent_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // ─── DESTROY ──────────────────────────────────────────────────────

    public function test_destroy_soft_deletes_non_conformity(): void
    {
        $nc = $this->createNC(['title' => 'To Delete']);

        $response = $this->deleteJson("/api/v1/non-conformities/{$nc->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('non_conformities', ['id' => $nc->id]);
    }

    // ─── AUTH ─────────────────────────────────────────────────────────

    public function test_unauthenticated_user_gets_401(): void
    {
        Sanctum::actingAs(new User, []);
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/non-conformities');

        $response->assertUnauthorized();
    }
}
