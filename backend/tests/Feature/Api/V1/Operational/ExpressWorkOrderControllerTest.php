<?php

namespace Tests\Feature\Api\V1\Operational;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpressWorkOrderControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/operational/work-orders/express', []);

        $response->assertStatus(422);
    }

    public function test_store_rejects_invalid_priority(): void
    {
        $response = $this->postJson('/api/v1/operational/work-orders/express', [
            'customer_name' => 'Cliente Novo',
            'description' => 'Manutenção de balança',
            'priority' => 'super-alto',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_creates_work_order_with_new_customer(): void
    {
        $response = $this->postJson('/api/v1/operational/work-orders/express', [
            'customer_name' => 'Cliente Express',
            'description' => 'Calibração urgente',
            'priority' => 'urgent',
        ]);

        $this->assertContains($response->status(), [200, 201]);
    }

    public function test_store_creates_work_order_with_existing_customer(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/operational/work-orders/express', [
            'customer_id' => $customer->id,
            'description' => 'Manutenção corretiva',
            'priority' => 'high',
        ]);

        $this->assertContains($response->status(), [200, 201]);
    }

    public function test_store_rejects_customer_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/operational/work-orders/express', [
            'customer_id' => $foreign->id,
            'description' => 'Teste cross-tenant',
            'priority' => 'normal',
        ]);

        $response->assertStatus(422);
    }
}
