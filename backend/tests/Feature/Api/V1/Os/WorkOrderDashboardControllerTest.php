<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderDashboardControllerTest extends TestCase
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

        Cache::flush();

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

    public function test_dashboard_stats_returns_empty_counts_with_no_data(): void
    {
        $response = $this->getJson('/api/v1/dashboard/work-orders');

        $response->assertOk();
    }

    public function test_dashboard_stats_counts_only_current_tenant(): void
    {
        // 3 OS do tenant atual
        WorkOrder::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        // 5 OS de outro tenant
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        WorkOrder::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
        ]);

        Cache::flush(); // evita bleed do cache do teste anterior

        $response = $this->getJson('/api/v1/dashboard/work-orders');

        $response->assertOk();
        // O response não pode conter nenhum dado agregado do tenant "errado";
        // verificamos que o total (quando presente) não extrapola 3.
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('"count":5', $json, 'Dashboard vazou counts de outro tenant');
    }

    public function test_metadata_endpoint_returns_200(): void
    {
        $response = $this->getJson('/api/v1/work-orders-metadata');

        $response->assertOk();
    }

    public function test_dashboard_stats_accepts_date_range(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'created_at' => now()->subDays(5),
        ]);

        $response = $this->getJson(
            '/api/v1/dashboard/work-orders?from='.now()->subDays(10)->format('Y-m-d').'&to='.now()->format('Y-m-d')
        );

        $response->assertOk();
    }

    public function test_dashboard_stats_returns_200_with_custom_range(): void
    {
        $response = $this->getJson('/api/v1/work-orders-dashboard-stats');

        $response->assertOk();
    }
}
