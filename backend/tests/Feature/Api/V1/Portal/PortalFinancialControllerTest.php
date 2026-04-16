<?php

namespace Tests\Feature\Api\V1\Portal;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalFinancialControllerTest extends TestCase
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

    public function test_index_returns_receivables_for_customer(): void
    {
        AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'description' => 'Fatura exemplo',
            'amount' => 500.00,
            'amount_paid' => 0,
            'due_date' => now()->addDays(15)->toDateString(),
            'status' => 'pending',
        ]);

        $response = $this->getJson("/api/v1/portal/financial/{$this->customer->id}");

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_index_returns_only_current_tenant_receivables(): void
    {
        $otherTenant = Tenant::factory()->create();
        AccountReceivable::create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $this->customer->id,
            'description' => 'LEAK fatura estranha',
            'amount' => 999999,
            'amount_paid' => 0,
            'due_date' => now()->addDays(10)->toDateString(),
            'status' => 'pending',
        ]);

        $response = $this->getJson("/api/v1/portal/financial/{$this->customer->id}");

        $response->assertOk();
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('LEAK fatura', $json);
        $this->assertStringNotContainsString('999999', $json);
    }

    public function test_index_with_nonexistent_customer_returns_empty(): void
    {
        $response = $this->getJson('/api/v1/portal/financial/99999');

        $response->assertOk()->assertJsonStructure(['data']);
        $this->assertCount(0, $response->json('data'));
    }
}
