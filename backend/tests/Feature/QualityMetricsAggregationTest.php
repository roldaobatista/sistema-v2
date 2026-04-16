<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CorrectiveAction;
use App\Models\Customer;
use App\Models\CustomerComplaint;
use App\Models\QualityAudit;
use App\Models\QualityCorrectiveAction;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QualityMetricsAggregationTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);
        Gate::before(fn () => true);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_quality_dashboard_aggregates_general_and_audit_corrective_actions(): void
    {
        $complaint = $this->createComplaint();

        CorrectiveAction::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'corrective',
            'source' => 'complaint',
            'sourceable_type' => CustomerComplaint::class,
            'sourceable_id' => $complaint->id,
            'nonconformity_description' => 'Falha geral',
            'status' => 'open',
            'deadline' => now()->subDay()->toDateString(),
        ]);

        $audit = QualityAudit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auditor_id' => $this->user->id,
        ]);

        QualityCorrectiveAction::create([
            'tenant_id' => $this->tenant->id,
            'quality_audit_id' => $audit->id,
            'description' => 'Nao conformidade de auditoria',
            'status' => QualityCorrectiveAction::STATUS_IN_PROGRESS,
            'due_date' => now()->subDay()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $this->getJson('/api/v1/quality/dashboard')
            ->assertOk()
            ->assertJsonPath('data.open_actions', 2)
            ->assertJsonPath('data.overdue_actions', 2);
    }

    public function test_quality_analytics_aggregates_action_aging_across_both_tables(): void
    {
        $complaint = $this->createComplaint();

        $action = CorrectiveAction::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'corrective',
            'source' => 'complaint',
            'sourceable_type' => CustomerComplaint::class,
            'sourceable_id' => $complaint->id,
            'nonconformity_description' => 'Falha geral',
            'status' => 'open',
            'deadline' => now()->addDays(3)->toDateString(),
        ]);
        $action->forceFill([
            'created_at' => now()->subDays(4),
            'updated_at' => now()->subDays(4),
        ])->saveQuietly();

        $audit = QualityAudit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auditor_id' => $this->user->id,
        ]);

        $auditAction = QualityCorrectiveAction::create([
            'tenant_id' => $this->tenant->id,
            'quality_audit_id' => $audit->id,
            'description' => 'Nao conformidade de auditoria',
            'status' => QualityCorrectiveAction::STATUS_OPEN,
            'due_date' => now()->subDay()->toDateString(),
            'created_by' => $this->user->id,
        ]);
        $auditAction->forceFill([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ])->saveQuietly();

        $this->getJson('/api/v1/quality/analytics')
            ->assertOk()
            ->assertJsonPath('data.actions_aging.total_open', 2)
            ->assertJsonPath('data.actions_aging.overdue', 1)
            ->assertJsonPath('data.actions_aging.due_7_days', 1);
    }

    public function test_quality_report_includes_audit_corrective_actions(): void
    {
        $audit = QualityAudit::factory()->create([
            'tenant_id' => $this->tenant->id,
            'auditor_id' => $this->user->id,
        ]);

        QualityCorrectiveAction::create([
            'tenant_id' => $this->tenant->id,
            'quality_audit_id' => $audit->id,
            'description' => 'Plano de acao da auditoria',
            'status' => QualityCorrectiveAction::STATUS_OPEN,
            'due_date' => now()->addDays(5)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $this->get('/api/v1/reports/peripheral/quality-audit')
            ->assertOk()
            ->assertSee('Plano de acao da auditoria')
            ->assertSee('audit');
    }

    public function test_store_corrective_action_requires_compatible_sourceable_origin(): void
    {
        $response = $this->postJson('/api/v1/quality/corrective-actions', [
            'type' => 'corrective',
            'source' => 'complaint',
            'sourceable_type' => QualityAudit::class,
            'sourceable_id' => 9999,
            'nonconformity_description' => 'Origem invalida',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sourceable_type']);
    }

    public function test_store_corrective_action_rejects_internal_source_with_bound_origin(): void
    {
        $complaint = $this->createComplaint();

        $response = $this->postJson('/api/v1/quality/corrective-actions', [
            'type' => 'corrective',
            'source' => 'internal',
            'sourceable_type' => CustomerComplaint::class,
            'sourceable_id' => $complaint->id,
            'nonconformity_description' => 'Nao deveria vincular',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sourceable_type']);
    }

    public function test_store_corrective_action_accepts_tenant_scoped_customer_complaint_origin(): void
    {
        $complaint = $this->createComplaint();

        $this->postJson('/api/v1/quality/corrective-actions', [
            'type' => 'corrective',
            'source' => 'complaint',
            'sourceable_type' => CustomerComplaint::class,
            'sourceable_id' => $complaint->id,
            'nonconformity_description' => 'Investigar causa',
        ])->assertCreated();

        $this->assertDatabaseHas('corrective_actions', [
            'tenant_id' => $this->tenant->id,
            'source' => 'complaint',
            'sourceable_type' => CustomerComplaint::class,
            'sourceable_id' => $complaint->id,
            'nonconformity_description' => 'Investigar causa',
        ]);
    }

    private function createComplaint(): CustomerComplaint
    {
        $customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        return CustomerComplaint::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'description' => 'Cliente relatou problema',
            'category' => 'service',
            'severity' => 'medium',
            'status' => 'open',
        ]);
    }
}
