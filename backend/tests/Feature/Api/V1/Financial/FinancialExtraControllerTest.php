<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinancialExtraControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        Sanctum::actingAs($this->user, ['*']);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    // ── PORTAL OVERVIEW ────────────────────────────────────────────────────

    public function test_portal_overview_returns_correct_structure(): void
    {
        $response = $this->getJson('/api/v1/financial-extra/portal-overview');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_receivable',
                    'total_overdue',
                    'total_received_month',
                    'avg_days_to_receive',
                    'customers_overdue',
                ],
            ]);
    }

    public function test_portal_overview_calculates_total_receivable(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => '500.00',
            'amount_paid' => '0.00',
            'status' => FinancialStatus::PENDING->value,
        ]);
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => '300.00',
            'amount_paid' => '0.00',
            'status' => FinancialStatus::PENDING->value,
        ]);

        $response = $this->getJson('/api/v1/financial-extra/portal-overview');

        $response->assertOk();
        $totalReceivable = $response->json('data.total_receivable');
        $this->assertEquals(800.00, (float) $totalReceivable);
    }

    public function test_portal_overview_excludes_paid_and_cancelled(): void
    {
        // Pending — should be included
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => '200.00',
            'amount_paid' => '0.00',
            'status' => FinancialStatus::PENDING->value,
        ]);
        // Paid — should be excluded
        AccountReceivable::factory()->paid()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => '1000.00',
        ]);
        // Cancelled — should be excluded
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => '500.00',
            'status' => FinancialStatus::CANCELLED->value,
        ]);

        $response = $this->getJson('/api/v1/financial-extra/portal-overview');

        $response->assertOk();
        $this->assertEquals(200.00, (float) $response->json('data.total_receivable'));
    }

    public function test_portal_overview_counts_overdue_customers(): void
    {
        $customer2 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Two overdue receivables for same customer — should count as 1
        AccountReceivable::factory()->overdue()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => '100.00',
            'amount_paid' => '0.00',
            'due_date' => now()->subDays(5)->format('Y-m-d'),
            'status' => FinancialStatus::OVERDUE->value,
        ]);
        AccountReceivable::factory()->overdue()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => '100.00',
            'amount_paid' => '0.00',
            'due_date' => now()->subDays(3)->format('Y-m-d'),
            'status' => FinancialStatus::OVERDUE->value,
        ]);
        // Second customer overdue
        AccountReceivable::factory()->overdue()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer2->id,
            'created_by' => $this->user->id,
            'amount' => '200.00',
            'amount_paid' => '0.00',
            'due_date' => now()->subDays(2)->format('Y-m-d'),
            'status' => FinancialStatus::OVERDUE->value,
        ]);

        $response = $this->getJson('/api/v1/financial-extra/portal-overview');

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.customers_overdue'));
    }

    public function test_portal_overview_tenant_isolation(): void
    {
        // Other tenant's receivable
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
            'amount' => '9999.00',
            'amount_paid' => '0.00',
            'status' => FinancialStatus::PENDING->value,
        ]);

        // Our tenant has nothing
        $response = $this->getJson('/api/v1/financial-extra/portal-overview');

        $response->assertOk();
        $this->assertEquals(0, (float) $response->json('data.total_receivable'));
    }

    public function test_portal_overview_requires_authentication(): void
    {
        // Reset auth
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/financial-extra/portal-overview');

        // 401 without valid auth token
        $response->assertUnauthorized();
    }

    // ── GENERATE BOLETO (422 — not configured) ─────────────────────────────

    public function test_generate_boleto_returns_422_not_configured(): void
    {
        // Find the boleto route
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson('/api/v1/financial-extra/boleto', [
            'receivable_id' => $ar->id,
        ]);
        $response->assertStatus(422)
            ->assertJsonPath('code', 'BOLETO_NOT_CONFIGURED');
    }

    // ── PAYMENT GATEWAY CONFIG ─────────────────────────────────────────────

    public function test_payment_gateway_config_returns_data(): void
    {
        $response = $this->getJson('/api/v1/financial-extra/payment-gateway-config');
        $response->assertOk()
            ->assertJsonStructure(['data']);
    }
}
