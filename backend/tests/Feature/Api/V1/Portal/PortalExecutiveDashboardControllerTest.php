<?php

namespace Tests\Feature\Api\V1\Portal;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalExecutiveDashboardControllerTest extends TestCase
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

    public function test_show_returns_dashboard_structure(): void
    {
        $response = $this->getJson("/api/v1/portal/dashboard/{$this->customer->id}");

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_show_aggregates_receivables_and_work_orders(): void
    {
        AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'description' => 'Fatura em aberto',
            'amount' => 1000.00,
            'amount_paid' => 0,
            'due_date' => now()->addDays(30)->toDateString(),
            'status' => 'pending',
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $response = $this->getJson("/api/v1/portal/dashboard/{$this->customer->id}");

        $response->assertOk();
        $this->assertIsArray($response->json('data'));
    }

    public function test_show_isolates_other_tenant_data(): void
    {
        $otherTenant = Tenant::factory()->create();
        AccountReceivable::create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $this->customer->id,
            'description' => 'Fatura estranha',
            'amount' => 888888,
            'amount_paid' => 0,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => 'pending',
        ]);

        $response = $this->getJson("/api/v1/portal/dashboard/{$this->customer->id}");

        $response->assertOk();
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('888888', $json);
    }
}
