<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SystemImprovementsAgingReportTest extends TestCase
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

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_aging_report_uses_open_overdue_balance_not_legacy_status_only(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 400.00,
            'status' => 'partial',
            'due_date' => now()->subDays(10),
        ]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 500.00,
            'amount_paid' => 0.00,
            'status' => 'pending',
            'due_date' => now()->subDays(40),
        ]);

        $response = $this->getJson('/api/v1/system/aging-report')->assertOk();

        $data = $response->json('data');
        $this->assertEquals(1100.0, $data['total_overdue']);
        $this->assertEquals(1, $data['total_customers']);
        $this->assertEquals(600.0, $data['aging']['0-30']['total_value']);
        $this->assertEquals(500.0, $data['aging']['31-60']['total_value']);
    }
}
