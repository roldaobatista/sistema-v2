<?php

namespace Tests\Feature\Api\V1\Quality;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\QualityAudit;
use App\Models\QualityAuditItem;
use App\Models\QualityCorrectiveAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QualityAuditControllerTest extends TestCase
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

    private function createAudit(array $overrides = []): QualityAudit
    {
        return QualityAudit::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'audit_number' => 'AUD-'.str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT),
            'title' => 'Auditoria Interna ISO 9001',
            'type' => 'internal',
            'planned_date' => now()->addDays(7)->toDateString(),
            'auditor_id' => $this->user->id,
            'status' => 'planned',
        ], $overrides));
    }

    private function createAuditItem(QualityAudit $audit, array $overrides = []): QualityAuditItem
    {
        return $audit->items()->create(array_merge([
            'requirement' => 'Conformidade SGQ',
            'clause' => '8.5.1',
            'question' => 'Instrucoes atualizadas?',
            'result' => null,
            'evidence' => null,
            'notes' => null,
        ], $overrides));
    }

    // ─── INDEX ────────────────────────────────────────────────────────

    public function test_index_returns_paginated_audits(): void
    {
        $this->createAudit(['title' => 'Audit A']);
        $this->createAudit(['title' => 'Audit B']);

        $response = $this->getJson('/api/v1/quality-audits');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_index_filters_by_search(): void
    {
        $this->createAudit(['title' => 'Auditoria Fornecedor ABC']);
        $this->createAudit(['title' => 'Auditoria Processo XYZ']);

        $response = $this->getJson('/api/v1/quality-audits?search=Fornecedor');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertStringContainsString('Fornecedor', $data[0]['title']);
    }

    public function test_index_filters_by_status(): void
    {
        $this->createAudit(['status' => 'planned']);
        $this->createAudit(['status' => 'completed']);

        $response = $this->getJson('/api/v1/quality-audits?status=completed');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('completed', $data[0]['status']);
    }

    public function test_index_filters_by_type(): void
    {
        $this->createAudit(['type' => 'internal']);
        $this->createAudit(['type' => 'supplier']);

        $response = $this->getJson('/api/v1/quality-audits?type=supplier');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('supplier', $data[0]['type']);
    }

    public function test_index_respects_tenant_isolation(): void
    {
        $this->createAudit(['title' => 'My Audit']);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        QualityAudit::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'audit_number' => 'AUD-99999',
            'title' => 'Other Tenant Audit',
            'type' => 'internal',
            'planned_date' => now()->addDays(5),
            'auditor_id' => $otherUser->id,
            'status' => 'planned',
        ]);

        $response = $this->getJson('/api/v1/quality-audits');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('My Audit', $titles);
        $this->assertNotContains('Other Tenant Audit', $titles);
    }

    // ─── STORE ────────────────────────────────────────────────────────

    public function test_store_creates_audit_with_default_items(): void
    {
        $response = $this->postJson('/api/v1/quality-audits', [
            'title' => 'Nova Auditoria',
            'type' => 'internal',
            'planned_date' => now()->addDays(14)->toDateString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Nova Auditoria')
            ->assertJsonPath('data.type', 'internal')
            ->assertJsonPath('data.status', 'planned');

        // Default items should be created for 'internal' type
        $auditId = $response->json('data.id');
        $items = QualityAuditItem::where('quality_audit_id', $auditId)->get();
        $this->assertGreaterThanOrEqual(3, $items->count());
    }

    public function test_store_creates_audit_with_custom_items(): void
    {
        $response = $this->postJson('/api/v1/quality-audits', [
            'title' => 'Auditoria com Itens',
            'type' => 'process',
            'planned_date' => now()->addDays(7)->toDateString(),
            'items' => [
                [
                    'question' => 'Procedimento seguido?',
                    'clause' => '7.1',
                    'result' => 'conform',
                ],
                [
                    'question' => 'Registros OK?',
                    'clause' => '7.5',
                ],
            ],
        ]);

        $response->assertStatus(201);
        $auditId = $response->json('data.id');
        $items = QualityAuditItem::where('quality_audit_id', $auditId)->get();
        $this->assertCount(2, $items);
        $this->assertEquals('Procedimento seguido?', $items[0]->question);
    }

    public function test_store_creates_supplier_audit_with_default_items(): void
    {
        $response = $this->postJson('/api/v1/quality-audits', [
            'title' => 'Audit Supplier',
            'type' => 'supplier',
            'planned_date' => now()->addDays(3)->toDateString(),
        ]);

        $response->assertStatus(201);
        $items = QualityAuditItem::where('quality_audit_id', $response->json('data.id'))->get();
        $this->assertCount(3, $items);
        $this->assertStringContainsString('fornecedor', strtolower($items[0]->requirement));
    }

    public function test_store_generates_audit_number(): void
    {
        $response = $this->postJson('/api/v1/quality-audits', [
            'title' => 'Audit Numbered',
            'type' => 'internal',
            'planned_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertStatus(201);
        $auditNumber = $response->json('data.audit_number');
        $this->assertStringStartsWith('AUD-', $auditNumber);
    }

    public function test_store_validation_requires_fields(): void
    {
        $response = $this->postJson('/api/v1/quality-audits', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'type']);
    }

    public function test_store_validation_rejects_invalid_type(): void
    {
        $response = $this->postJson('/api/v1/quality-audits', [
            'title' => 'Audit',
            'type' => 'invalid_type',
            'planned_date' => now()->addDays(5)->toDateString(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_accepts_scheduled_date_alias(): void
    {
        $date = now()->addDays(10)->toDateString();

        $response = $this->postJson('/api/v1/quality-audits', [
            'title' => 'Alias Test',
            'type' => 'external',
            'scheduled_date' => $date,
        ]);

        $response->assertStatus(201);
        $this->assertStringContainsString($date, $response->json('data.planned_date'));
    }

    // ─── SHOW ─────────────────────────────────────────────────────────

    public function test_show_returns_audit_with_relations(): void
    {
        $audit = $this->createAudit(['title' => 'Detalhes Audit']);
        $this->createAuditItem($audit);

        $response = $this->getJson("/api/v1/quality-audits/{$audit->id}");

        $response->assertOk()
            ->assertJsonPath('data.title', 'Detalhes Audit')
            ->assertJsonPath('data.auditor.id', $this->user->id)
            ->assertJsonStructure(['data' => ['items']]);
    }

    public function test_show_returns_403_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $audit = QualityAudit::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'audit_number' => 'AUD-ALIEN',
            'title' => 'Alien Audit',
            'type' => 'internal',
            'planned_date' => now()->addDays(5),
            'auditor_id' => $otherUser->id,
            'status' => 'planned',
        ]);

        $response = $this->getJson("/api/v1/quality-audits/{$audit->id}");

        // Model binding with BelongsToTenant scope will 404
        $response->assertStatus(404);
    }

    // ─── UPDATE ───────────────────────────────────────────────────────

    public function test_update_modifies_audit(): void
    {
        $audit = $this->createAudit(['title' => 'Original', 'status' => 'planned']);

        $response = $this->putJson("/api/v1/quality-audits/{$audit->id}", [
            'title' => 'Updated Title',
            'status' => 'in_progress',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.status', 'in_progress');
    }

    public function test_update_maps_scheduled_date_to_planned_date(): void
    {
        $audit = $this->createAudit();
        $newDate = now()->addMonth()->toDateString();

        $response = $this->putJson("/api/v1/quality-audits/{$audit->id}", [
            'scheduled_date' => $newDate,
        ]);

        $response->assertOk();
        $this->assertStringContainsString($newDate, $response->json('data.planned_date'));
    }

    public function test_update_validates_status_values(): void
    {
        $audit = $this->createAudit();

        $response = $this->putJson("/api/v1/quality-audits/{$audit->id}", [
            'status' => 'nonexistent_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // ─── DESTROY ──────────────────────────────────────────────────────

    public function test_destroy_deletes_audit(): void
    {
        $audit = $this->createAudit(['title' => 'To Delete']);

        $response = $this->deleteJson("/api/v1/quality-audits/{$audit->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('quality_audits', ['id' => $audit->id]);
    }

    // ─── UPDATE ITEM ──────────────────────────────────────────────────

    public function test_update_item_modifies_audit_item(): void
    {
        $audit = $this->createAudit();
        $item = $this->createAuditItem($audit);

        $response = $this->putJson("/api/v1/quality-audits/{$audit->id}/items/{$item->id}", [
            'result' => 'conform',
            'evidence' => 'Foto do processo OK',
            'notes' => 'Sem observacoes',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.result', 'conform')
            ->assertJsonPath('data.evidence', 'Foto do processo OK');
    }

    public function test_update_item_normalizes_conforming_to_conform(): void
    {
        $audit = $this->createAudit();
        $item = $this->createAuditItem($audit);

        $response = $this->putJson("/api/v1/quality-audits/{$audit->id}/items/{$item->id}", [
            'result' => 'conforming',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.result', 'conform');
    }

    public function test_update_item_maps_description_to_question(): void
    {
        $audit = $this->createAudit();
        $item = $this->createAuditItem($audit);

        $response = $this->putJson("/api/v1/quality-audits/{$audit->id}/items/{$item->id}", [
            'description' => 'Nova pergunta via description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.question', 'Nova pergunta via description');
    }

    public function test_update_item_maps_status_to_result(): void
    {
        $audit = $this->createAudit();
        $item = $this->createAuditItem($audit);

        $response = $this->putJson("/api/v1/quality-audits/{$audit->id}/items/{$item->id}", [
            'status' => 'non_conform',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.result', 'non_conform');
    }

    // ─── CORRECTIVE ACTIONS ──────────────────────────────────────────

    public function test_index_corrective_actions(): void
    {
        $audit = $this->createAudit();
        $item = $this->createAuditItem($audit, ['result' => 'non_conform']);

        QualityCorrectiveAction::create([
            'tenant_id' => $this->tenant->id,
            'quality_audit_id' => $audit->id,
            'quality_audit_item_id' => $item->id,
            'description' => 'Acao corretiva 1',
            'status' => QualityCorrectiveAction::STATUS_OPEN,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/quality-audits/{$audit->id}/corrective-actions");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Acao corretiva 1', $data[0]['description']);
    }

    public function test_store_corrective_action(): void
    {
        $audit = $this->createAudit();
        $item = $this->createAuditItem($audit, ['result' => 'non_conform']);

        $response = $this->postJson("/api/v1/quality-audits/{$audit->id}/corrective-actions", [
            'description' => 'Retreinamento da equipe',
            'root_cause' => 'Falta de treinamento',
            'quality_audit_item_id' => $item->id,
            'responsible_id' => $this->user->id,
            'due_date' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('quality_corrective_actions', [
            'quality_audit_id' => $audit->id,
            'description' => 'Retreinamento da equipe',
            'status' => QualityCorrectiveAction::STATUS_OPEN,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_store_corrective_action_validation(): void
    {
        $audit = $this->createAudit();

        $response = $this->postJson("/api/v1/quality-audits/{$audit->id}/corrective-actions", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['description']);
    }

    // ─── CLOSE ITEM ──────────────────────────────────────────────────

    public function test_close_item_sets_conform_and_completes_actions(): void
    {
        $audit = $this->createAudit();
        $item = $this->createAuditItem($audit, ['result' => 'non_conform']);

        $action = QualityCorrectiveAction::create([
            'tenant_id' => $this->tenant->id,
            'quality_audit_id' => $audit->id,
            'quality_audit_item_id' => $item->id,
            'description' => 'Acao pendente',
            'status' => QualityCorrectiveAction::STATUS_OPEN,
            'created_by' => $this->user->id,
        ]);

        $response = $this->patchJson("/api/v1/quality-audits/{$audit->id}/items/{$item->id}/close", [
            'evidence' => 'Evidencia de fechamento',
            'action_taken' => 'Treinamento realizado',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.result', 'conform')
            ->assertJsonPath('data.evidence', 'Evidencia de fechamento');

        $action->refresh();
        $this->assertEquals(QualityCorrectiveAction::STATUS_COMPLETED, $action->status);
        $this->assertEquals('Treinamento realizado', $action->action_taken);
        $this->assertNotNull($action->completed_at);
    }

    public function test_close_item_requires_evidence(): void
    {
        $audit = $this->createAudit();
        $item = $this->createAuditItem($audit, ['result' => 'non_conform']);

        $response = $this->patchJson("/api/v1/quality-audits/{$audit->id}/items/{$item->id}/close", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['evidence']);
    }

    // ─── AUTH ─────────────────────────────────────────────────────────

    public function test_unauthenticated_user_gets_401(): void
    {
        Sanctum::actingAs(new User, []);
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/quality-audits');

        $response->assertUnauthorized();
    }
}
