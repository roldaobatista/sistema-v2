<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderCriticalAnalysisTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_create_work_order_with_critical_analysis_fields(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'description' => 'Calibração de balança de precisão',
            'service_type' => 'calibracao',
            'priority' => 'normal',
            'service_modality' => 'calibracao',
            'requires_adjustment' => true,
            'requires_maintenance' => false,
            'client_wants_conformity_declaration' => true,
            'decision_rule_agreed' => 'guard_band',
            'subject_to_legal_metrology' => true,
            'needs_ipem_interaction' => true,
            'site_conditions' => 'Ambiente com temperatura controlada 20-25°C',
            'calibration_scope_notes' => 'Calibração de 0 a 10000kg, 5 pontos de ensaio',
            'will_emit_complementary_report' => false,
        ];

        $response = $this->postJson('/api/v1/work-orders', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('work_orders', [
            'service_type' => 'calibracao',
            'service_modality' => 'calibracao',
            'requires_adjustment' => true,
            'requires_maintenance' => false,
            'client_wants_conformity_declaration' => true,
            'decision_rule_agreed' => 'guard_band',
            'subject_to_legal_metrology' => true,
            'needs_ipem_interaction' => true,
            'site_conditions' => 'Ambiente com temperatura controlada 20-25°C',
            'calibration_scope_notes' => 'Calibração de 0 a 10000kg, 5 pontos de ensaio',
            'will_emit_complementary_report' => false,
        ]);
    }

    public function test_update_work_order_critical_analysis_fields(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'calibracao',
        ]);

        $response = $this->putJson("/api/v1/work-orders/{$workOrder->id}", [
            'service_modality' => 'ajuste',
            'requires_maintenance' => true,
            'client_wants_conformity_declaration' => false,
            'decision_rule_agreed' => null,
            'subject_to_legal_metrology' => false,
            'site_conditions' => 'Ambiente externo',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('work_orders', [
            'id' => $workOrder->id,
            'service_modality' => 'ajuste',
            'requires_maintenance' => true,
            'client_wants_conformity_declaration' => false,
            'subject_to_legal_metrology' => false,
            'site_conditions' => 'Ambiente externo',
        ]);
    }

    public function test_decision_rule_agreed_is_nullable(): void
    {
        $payload = [
            'customer_id' => $this->customer->id,
            'description' => 'Calibração sem declaração de conformidade',
            'service_type' => 'calibracao',
            'priority' => 'normal',
            'client_wants_conformity_declaration' => false,
            'decision_rule_agreed' => null,
        ];

        $response = $this->postJson('/api/v1/work-orders', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('work_orders', [
            'client_wants_conformity_declaration' => false,
            'decision_rule_agreed' => null,
        ]);
    }

    public function test_critical_analysis_fields_returned_in_response(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'service_type' => 'calibracao',
            'service_modality' => 'calibracao',
            'requires_adjustment' => true,
            'client_wants_conformity_declaration' => true,
            'decision_rule_agreed' => 'simple',
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$workOrder->id}");

        $response->assertOk()
            ->assertJsonPath('data.service_modality', 'calibracao')
            ->assertJsonPath('data.requires_adjustment', true)
            ->assertJsonPath('data.client_wants_conformity_declaration', true)
            ->assertJsonPath('data.decision_rule_agreed', 'simple');
    }
}
