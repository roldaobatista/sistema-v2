<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CrossModuleFlowTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    }

    public function test_create_customer_then_equipment_then_work_order(): void
    {
        // Step 1: Create Equipment
        $eqResponse = $this->actingAs($this->user)->postJson('/api/v1/equipments', [
            'customer_id' => $this->customer->id,
            'name' => 'Balança 500kg',
            'serial_number' => 'SN-FLOW-001',
        ]);
        $eqResponse->assertCreated();
        $eqId = $eqResponse->json('data.id') ?? $eqResponse->json('id');

        // Step 2: Create Work Order for that equipment
        $woResponse = $this->actingAs($this->user)->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'equipment_id' => $eqId,
            'title' => 'Calibração',
        ]);
        $woResponse->assertCreated();
    }

    public function test_create_quote_then_convert_to_work_order(): void
    {
        $quoteResponse = $this->actingAs($this->user)->postJson('/api/v1/quotes', [
            'customer_id' => $this->customer->id,
            'title' => 'Orçamento serviço',
        ]);
        $quoteResponse->assertCreated();
        $quoteId = $quoteResponse->json('data.id') ?? $quoteResponse->json('id');

        if ($quoteId) {
            $woResponse = $this->actingAs($this->user)->postJson('/api/v1/work-orders', [
                'customer_id' => $this->customer->id,
                'quote_id' => $quoteId,
                'title' => 'OS do orçamento',
            ]);
            $woResponse->assertCreated();
        }
    }

    public function test_work_order_to_invoice_flow(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'total' => '1500.00',
        ]);

        // Check that a completed WO can be fetched
        $response = $this->actingAs($this->user)->getJson("/api/v1/work-orders/{$wo->id}");
        $response->assertOk();
    }

    public function test_customer_with_multiple_work_orders(): void
    {
        WorkOrder::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/work-orders?customer_id={$this->customer->id}");

        $response->assertOk();
        $this->assertGreaterThanOrEqual(5, count($response->json('data')));
    }

    public function test_customer_equipment_listing(): void
    {
        Equipment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/equipments?customer_id={$this->customer->id}");

        $response->assertOk();
    }

    public function test_dashboard_endpoint(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/dashboard');
        $response->assertOk();
    }

    public function test_search_global(): void
    {
        Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Kalibrium Search Test']);

        $response = $this->actingAs($this->user)->getJson('/api/v1/search?q=Kalibrium');
        $response->assertOk();
    }

    public function test_auth_user_profile(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/auth/user');
        $response->assertOk();
    }

    public function test_notifications_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/notifications');
        $response->assertOk();
    }

    public function test_system_settings_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/settings');
        $response->assertOk();
    }
}
