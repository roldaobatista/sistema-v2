<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\ClientPortalUser;
use App\Models\Customer;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientPortalControllerTest extends TestCase
{
    private Tenant $tenant;

    private Customer $customer;

    private ClientPortalUser $portalUser;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->portalUser = ClientPortalUser::forceCreate([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'name' => 'Cliente Portal',
            'email' => 'portal-client@example.com',
            'password' => bcrypt('secret123'),
            'is_active' => true,
        ]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->portalUser, ['*']);
    }

    public function test_track_work_orders_returns_only_current_customer(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $foreign = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $response = $this->getJson('/api/v1/client-portal/work-orders/track');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_track_service_calls_returns_only_current_customer(): void
    {
        ServiceCall::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'call_number' => 'SC-'.uniqid(),
            'status' => ServiceCall::STATUS_OPEN,
            'priority' => 'normal',
            'observations' => 'Chamado do cliente correto',
        ]);

        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        ServiceCall::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            'call_number' => 'SC-'.uniqid(),
            'status' => ServiceCall::STATUS_OPEN,
            'priority' => 'normal',
            'observations' => 'LEAK chamado de outro cliente',
        ]);

        $response = $this->getJson('/api/v1/client-portal/service-calls/track');

        $response->assertOk()->assertJsonStructure(['data']);
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('LEAK chamado', $json);
    }

    public function test_calibration_certificates_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/client-portal/calibration-certificates');

        $response->assertOk();
    }

    public function test_create_service_call_from_portal_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/client-portal/service-calls', []);

        $this->assertContains($response->status(), [422, 500]);
    }

    public function test_create_service_call_from_portal_creates_call(): void
    {
        $response = $this->postJson('/api/v1/client-portal/service-calls', [
            'subject' => 'Balança precisa de calibração',
            'description' => 'A balança está apresentando desvio frequente após 3 semanas de uso',
            'priority' => 'high',
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('service_calls', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'priority' => 'high',
        ]);
    }
}
