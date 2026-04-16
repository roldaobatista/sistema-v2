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

class WorkOrderFinalizeValidationTest extends TestCase
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
        ]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createWorkOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
        ], $overrides));
    }

    public function test_cannot_finalize_work_order_not_in_service(): void
    {
        $wo = $this->createWorkOrder(['status' => WorkOrder::STATUS_OPEN]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/execution/finalize", [
            'technical_report' => 'Relatório técnico completo.',
        ]);

        $response->assertStatus(422);
    }

    public function test_finalize_requires_technical_report_when_service_type_is_not_diagnostico(): void
    {
        $wo = $this->createWorkOrder([
            'status' => WorkOrder::STATUS_IN_SERVICE,
            'service_type' => 'manutencao',
            'service_started_at' => now()->subHour(),
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/execution/finalize", [
            'technical_report' => '',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['technical_report']);
    }

    public function test_finalize_succeeds_with_valid_data_when_status_is_in_service(): void
    {
        $wo = $this->createWorkOrder([
            'status' => WorkOrder::STATUS_IN_SERVICE,
            'service_type' => 'manutencao',
            'service_started_at' => now()->subHour(),
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/execution/finalize", [
            'technical_report' => 'Serviço realizado com sucesso. Troca de peça X.',
            'resolution_notes' => 'Cliente satisfeito com o resultado.',
        ]);

        $response->assertStatus(200);
    }
}
