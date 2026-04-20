<?php

namespace Tests\Feature\Api\V1\Portal;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsurePortalAccess;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\ClientPortalUser;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalWorkOrderTest extends TestCase
{
    private Tenant $tenant;

    private User $internalUser;

    private ClientPortalUser $portalUser;

    private Customer $customer;

    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
            EnsurePortalAccess::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->portalUser = ClientPortalUser::forceCreate([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'name' => 'Portal User',
            'email' => 'portal@example.com',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $this->internalUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->portalUser, ['*']);

        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->internalUser->id,
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);
    }

    public function test_portal_can_list_own_work_orders(): void
    {
        $response = $this->getJson('/api/v1/portal/work-orders');

        $response->assertOk()
            ->assertJsonMissingPath('data.0.tenant_id')
            ->assertJsonMissingPath('data.0.created_by')
            ->assertJsonMissingPath('data.0.internal_notes')
            ->assertJsonMissingPath('data.0.technical_report')
            ->assertJsonMissingPath('data.0.signature_ip');
    }

    public function test_portal_work_order_show_hides_internal_fields(): void
    {
        $this->workOrder->forceFill([
            'internal_notes' => 'Somente equipe interna',
            'technical_report' => 'Diagnostico tecnico interno',
            'signature_ip' => '203.0.113.10',
        ])->save();
        WorkOrderItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'type' => WorkOrderItem::TYPE_SERVICE,
            'description' => 'Servico visivel',
            'quantity' => 1,
            'unit_price' => 120,
            'cost_price' => 30,
            'warehouse_id' => 999,
        ]);

        $response = $this->getJson("/api/v1/portal/work-orders/{$this->workOrder->id}");

        $response->assertOk()
            ->assertJsonMissingPath('data.tenant_id')
            ->assertJsonMissingPath('data.created_by')
            ->assertJsonMissingPath('data.internal_notes')
            ->assertJsonMissingPath('data.technical_report')
            ->assertJsonMissingPath('data.signature_ip')
            ->assertJsonMissingPath('data.items.0.tenant_id')
            ->assertJsonMissingPath('data.items.0.cost_price')
            ->assertJsonMissingPath('data.items.0.warehouse_id')
            ->assertJsonPath('data.items.0.description', 'Servico visivel')
            ->assertJsonPath('data.id', $this->workOrder->id);
    }

    public function test_portal_cannot_see_other_customer_work_orders(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $this->internalUser->id,
        ]);

        $response = $this->getJson('/api/v1/portal/work-orders');

        $response->assertOk();
        $ids = collect($response->json('data.data') ?? $response->json('data'))->pluck('id')->toArray();
        $this->assertContains($this->workOrder->id, $ids);
        $this->assertNotContains($otherWo->id, $ids);
    }

    public function test_portal_can_view_work_order_photos(): void
    {
        $response = $this->getJson("/api/v1/portal/work-orders/{$this->workOrder->id}/photos");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['attachments', 'photo_checklist']]);
    }

    public function test_portal_financials_hide_internal_fields(): void
    {
        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->internalUser->id,
            'description' => 'Mensalidade visivel',
        ]);

        $response = $this->getJson('/api/v1/portal/financials');

        $response->assertOk()
            ->assertJsonPath('data.0.id', $receivable->id)
            ->assertJsonPath('data.0.description', 'Mensalidade visivel')
            ->assertJsonMissingPath('data.0.tenant_id')
            ->assertJsonMissingPath('data.0.customer_id')
            ->assertJsonMissingPath('data.0.created_by')
            ->assertJsonMissingPath('data.0.chart_of_account_id');
    }

    public function test_portal_service_call_response_hides_internal_fields(): void
    {
        $response = $this->postJson('/api/v1/portal/service-calls', [
            'description' => 'Preciso de suporte tecnico no equipamento principal.',
            'priority' => 'normal',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending_scheduling')
            ->assertJsonMissingPath('data.tenant_id')
            ->assertJsonMissingPath('data.customer_id')
            ->assertJsonMissingPath('data.created_by')
            ->assertJsonMissingPath('data.resolution_notes');
    }

    public function test_portal_can_submit_signature(): void
    {
        $response = $this->postJson("/api/v1/portal/work-orders/{$this->workOrder->id}/signature", [
            'signer_name' => 'João Cliente',
            'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.signer_name', 'João Cliente')
            ->assertJsonMissingPath('data.tenant_id')
            ->assertJsonMissingPath('data.signature_data')
            ->assertJsonMissingPath('data.ip_address')
            ->assertJsonMissingPath('data.user_agent');

        $this->assertDatabaseHas('work_order_signatures', [
            'work_order_id' => $this->workOrder->id,
            'signer_name' => 'João Cliente',
            'signer_type' => 'customer',
        ]);
    }

    public function test_portal_signature_requires_name_and_data(): void
    {
        $response = $this->postJson("/api/v1/portal/work-orders/{$this->workOrder->id}/signature", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['signer_name', 'signature_data']);
    }
}
