<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RemainingModulesFinancialPortalTest extends TestCase
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

    public function test_portal_overview_uses_open_balance_and_payment_date(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 0,
            'status' => 'pending',
            'due_date' => now()->subDays(5),
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'received_by' => $this->user->id,
            'amount' => 400.00,
            'payment_date' => now()->toDateString(),
        ]);

        $data = $this->getJson('/api/v1/financial-extra/portal-overview')
            ->assertOk()
            ->json('data');

        $this->assertEquals(600.0, $data['total_receivable']);
        $this->assertEquals(600.0, $data['total_overdue']);
        $this->assertEquals(400.0, $data['total_received_month']);
        $this->assertEquals(1, $data['customers_overdue']);
    }

    public function test_portal_overview_ignores_renegotiated_receivables_in_open_totals(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 900.00,
            'amount_paid' => 100.00,
            'status' => 'renegotiated',
            'due_date' => now()->subDays(20),
        ]);

        $data = $this->getJson('/api/v1/financial-extra/portal-overview')
            ->assertOk()
            ->json('data');

        $this->assertEquals(0.0, $data['total_receivable']);
        $this->assertEquals(0.0, $data['total_overdue']);
        $this->assertEquals(0, $data['customers_overdue']);
    }

    public function test_presentation_data_uses_payment_date_for_revenue_year(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1200.00,
            'amount_paid' => 1200.00,
            'status' => 'paid',
            'paid_at' => now()->copy()->subYear()->toDateString(),
            'due_date' => now()->copy()->subYear()->subDays(5)->toDateString(),
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'received_by' => $this->user->id,
            'amount' => 1200.00,
            'payment_date' => now()->toDateString(),
        ]);

        $data = $this->getJson('/api/v1/innovation/presentation')
            ->assertOk()
            ->json('data');

        $this->assertEquals(1200.0, $data['kpis']['revenue_year']);
    }
}
