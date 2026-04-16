<?php

namespace Tests\Feature\Api\V1\Crm;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmContractRenewal;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmContractControllerTest extends TestCase
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

    private function createRenewal(?int $tenantId = null, string $status = 'pending'): CrmContractRenewal
    {
        return CrmContractRenewal::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'customer_id' => $this->customer->id,
            'contract_end_date' => now()->addDays(30)->toDateString(),
            'alert_days_before' => 30,
            'status' => $status,
            'current_value' => 12000.00,
            'notes' => 'Renovação de contrato',
        ]);
    }

    public function test_contract_renewals_returns_only_current_tenant(): void
    {
        $mine = $this->createRenewal();

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = CrmContractRenewal::create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'contract_end_date' => now()->addDays(30)->toDateString(),
            'alert_days_before' => 30,
            'status' => 'pending',
            'current_value' => 9999.99,
        ]);

        $response = $this->getJson('/api/v1/crm-features/renewals');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_contract_renewals_filters_by_status(): void
    {
        $this->createRenewal(null, 'pending');
        $this->createRenewal(null, 'renewed');

        $response = $this->getJson('/api/v1/crm-features/renewals?status=renewed');

        $response->assertOk();
        foreach ($response->json('data') as $row) {
            $this->assertSame('renewed', $row['status']);
        }
    }

    public function test_update_renewal_updates_status(): void
    {
        $renewal = $this->createRenewal();

        $response = $this->putJson("/api/v1/crm-features/renewals/{$renewal->id}", [
            'status' => 'renewed',
            'renewal_value' => 15000.00,
            'notes' => 'Renovação concluída',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('crm_contract_renewals', [
            'id' => $renewal->id,
            'status' => 'renewed',
        ]);
    }

    public function test_generate_renewals_endpoint_is_reachable(): void
    {
        $response = $this->postJson('/api/v1/crm-features/renewals/generate');

        // Endpoint executa job de geração — aceita 200 (sucesso) ou 422 (sem dados)
        $this->assertContains($response->status(), [200, 201, 422]);
    }
}
