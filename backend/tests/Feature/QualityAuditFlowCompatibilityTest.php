<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\QualityAudit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QualityAuditFlowCompatibilityTest extends TestCase
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
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_store_accepts_planned_date_and_generates_default_items(): void
    {
        $plannedDate = now()->addDays(7)->toDateString();

        $response = $this->postJson('/api/v1/quality-audits', [
            'title' => 'Auditoria Compatibilidade',
            'type' => 'internal',
            'scope' => 'Fluxo de qualidade',
            'planned_date' => $plannedDate,
            'auditor_id' => $this->user->id,
        ])->assertStatus(201);

        $auditId = (int) $response->json('data.id');

        $this->assertDatabaseHas('quality_audits', [
            'id' => $auditId,
            'tenant_id' => $this->tenant->id,
            'title' => 'Auditoria Compatibilidade',
        ]);

        $storedPlannedDate = (string) DB::table('quality_audits')
            ->where('id', $auditId)
            ->value('planned_date');
        $this->assertStringStartsWith($plannedDate, $storedPlannedDate);

        $this->assertSame(
            3,
            DB::table('quality_audit_items')->where('quality_audit_id', $auditId)->count()
        );
    }

    public function test_update_item_accepts_legacy_status_alias_and_maps_result(): void
    {
        $audit = QualityAudit::create([
            'tenant_id' => $this->tenant->id,
            'audit_number' => 'AUD-00999',
            'title' => 'Auditoria de Mapeamento',
            'type' => 'internal',
            'scope' => 'Teste',
            'planned_date' => now()->toDateString(),
            'auditor_id' => $this->user->id,
            'status' => 'planned',
        ]);

        $item = $audit->items()->create([
            'requirement' => 'Controle',
            'question' => 'Requisito atendido?',
            'result' => null,
        ]);

        $this->putJson("/api/v1/quality-audits/{$audit->id}/items/{$item->id}", [
            'status' => 'non_conforming',
            'notes' => 'Desvio encontrado',
        ])->assertOk()
            ->assertJsonPath('data.result', 'non_conform')
            ->assertJsonPath('data.notes', 'Desvio encontrado');

        $item->refresh();
        $this->assertSame('non_conform', $item->result);
    }
}
