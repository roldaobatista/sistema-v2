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

class WorkOrderDashboardTest extends TestCase
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
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_dashboard_stats_returns_correct_metrics(): void
    {
        // Limpar cache antes do teste
        Cache::flush();

        // OS normal completada
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'total' => '200.00',
            'created_at' => now(),
            'started_at' => now()->subHours(2),
            'completed_at' => now(),
            'service_type' => 'preventive',
            'sla_due_at' => now()->addDays(1), // Não estourada
        ]);

        // OS atrasada
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'total' => '100.00',
            'created_at' => now(),
            'service_type' => 'corrective',
            'sla_due_at' => now()->subDays(1), // Estourada!
        ]);

        // OS faturada
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_INVOICED,
            'total' => '1500.00',
            'created_at' => now(),
            'service_type' => 'installation',
        ]);

        $response = $this->getJson('/api/v1/work-orders-dashboard-stats');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertEquals(3, $data['total_orders']);
        $this->assertEquals(1500.00, (float) $data['month_revenue']);

        // Verifica se tem 1 OS atrasada
        $this->assertEquals(1, $data['overdue_orders']);

        // Verifica SLA (2 com SLA setado, 1 estourada -> 50%)
        // Somente 2 OSs criadas tem `sla_due_at`
        $this->assertEquals(50.0, (float) $data['sla_compliance']);

        // Verifica array de service types
        $this->assertArrayHasKey('preventive', $data['service_type_counts']);
        $this->assertEquals(1, $data['service_type_counts']['preventive']);
        $this->assertArrayHasKey('corrective', $data['service_type_counts']);
        $this->assertEquals(1, $data['service_type_counts']['corrective']);

        // Verifica status_counts
        $this->assertEquals(1, $data['status_counts'][WorkOrder::STATUS_COMPLETED]);
        $this->assertEquals(1, $data['status_counts'][WorkOrder::STATUS_IN_PROGRESS]);

        // Verifica Top Customers
        $this->assertCount(1, $data['top_customers']);
        $this->assertEquals($this->customer->name, $data['top_customers'][0]['name']);
        $this->assertEquals(3, $data['top_customers'][0]['total_os']);
    }

    public function test_dashboard_stats_respects_tenant(): void
    {
        Cache::flush();
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'status' => WorkOrder::STATUS_COMPLETED,
            'total' => '500.00',
        ]);

        $response = $this->getJson('/api/v1/work-orders-dashboard-stats');
        $response->assertOk();
        $data = $response->json('data');

        $this->assertEquals(0, $data['total_orders']);
        $this->assertEquals(0, $data['overdue_orders']);
        $this->assertCount(0, $data['top_customers']);
    }
}
