<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceCallControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createServiceCall(?int $tenantId = null, ?int $customerId = null): ServiceCall
    {
        return ServiceCall::factory()->create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'customer_id' => $customerId ?? $this->customer->id,
        ]);
    }

    public function test_index_returns_only_current_tenant_service_calls(): void
    {
        $this->createServiceCall();

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = $this->createServiceCall($otherTenant->id, $otherCustomer->id);

        $response = $this->getJson('/api/v1/service-calls');

        $response->assertOk();
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('"id":'.$foreign->id.',"tenant_id":'.$otherTenant->id, $json);
    }

    public function test_store_validates_required_customer(): void
    {
        $response = $this->postJson('/api/v1/service-calls', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_rejects_customer_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/service-calls', [
            'customer_id' => $foreignCustomer->id,
            'description' => 'Cross-tenant attempt',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_show_returns_404_for_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = $this->createServiceCall($otherTenant->id, $otherCustomer->id);

        $response = $this->getJson("/api/v1/service-calls/{$foreign->id}");

        $response->assertStatus(404);
    }

    public function test_destroy_returns_404_for_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = $this->createServiceCall($otherTenant->id, $otherCustomer->id);

        $response = $this->deleteJson("/api/v1/service-calls/{$foreign->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('service_calls', ['id' => $foreign->id]);
    }
}
