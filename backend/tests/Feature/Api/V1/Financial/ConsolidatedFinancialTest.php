<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsolidatedFinancialTest extends TestCase
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
        // Attach user to tenant via pivot
        $this->user->tenants()->syncWithoutDetaching([$this->tenant->id]);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── BASIC ─────────────────────────────────────────────────

    public function test_consolidated_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/financial/consolidated');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'period',
                    'totals' => [
                        'receivables_open',
                        'receivables_overdue',
                        'received_month',
                        'payables_open',
                        'payables_overdue',
                        'paid_month',
                        'expenses_month',
                        'invoiced_month',
                    ],
                    'balance',
                    'per_tenant',
                ],
            ]);
    }

    public function test_consolidated_includes_receivables_open(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING,
            'due_date' => now()->addWeek(),
        ]);
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 500.00,
            'amount_paid' => 200.00,
            'status' => FinancialStatus::PARTIAL,
            'due_date' => now()->addWeek(),
        ]);

        $response = $this->getJson('/api/v1/financial/consolidated');

        $response->assertOk();
        $totals = $response->json('data.totals');
        // Open total = (1000 - 0) + (500 - 200) = 1300
        $this->assertEquals(1300.0, $totals['receivables_open']);
    }

    public function test_consolidated_includes_receivables_overdue(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        // Overdue receivable
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 800.00,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING,
            'due_date' => now()->subWeek(),
        ]);
        // Not overdue
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 600.00,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING,
            'due_date' => now()->addMonth(),
        ]);

        $response = $this->getJson('/api/v1/financial/consolidated');

        $response->assertOk();
        $totals = $response->json('data.totals');
        $this->assertEquals(800.0, $totals['receivables_overdue']);
    }

    public function test_consolidated_includes_received_this_month(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1500.00,
            'amount_paid' => 1500.00,
            'status' => FinancialStatus::PAID,
            'due_date' => now()->subMonth(),
            'paid_at' => now()->subMonth(),
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'received_by' => $this->user->id,
            'amount' => 1500.00,
            'payment_date' => now()->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/financial/consolidated');

        $response->assertOk();
        $this->assertEquals(1500.0, $response->json('data.totals.received_month'));
    }

    public function test_consolidated_includes_payables_open(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 2000.00,
            'amount_paid' => 500.00,
            'status' => FinancialStatus::PENDING,
            'due_date' => now()->addWeek(),
        ]);

        $response = $this->getJson('/api/v1/financial/consolidated');

        $response->assertOk();
        $this->assertEquals(1500.0, $response->json('data.totals.payables_open'));
    }

    public function test_consolidated_includes_payables_overdue(): void
    {
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 750.00,
            'status' => FinancialStatus::PENDING,
            'due_date' => now()->subDays(5),
        ]);

        $response = $this->getJson('/api/v1/financial/consolidated');

        $response->assertOk();
        $this->assertEquals(750.0, $response->json('data.totals.payables_overdue'));
    }

    public function test_consolidated_includes_expenses_this_month(): void
    {
        Expense::factory()->approved()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 300.00,
            'expense_date' => now()->format('Y-m-d'),
        ]);

        $response = $this->getJson('/api/v1/financial/consolidated');

        $response->assertOk();
        $this->assertEquals(300.0, $response->json('data.totals.expenses_month'));
    }

    public function test_consolidated_excludes_cancelled_receivables_and_payables(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 5000.00,
            'amount_paid' => 0,
            'status' => FinancialStatus::CANCELLED,
            'due_date' => now()->addWeek(),
        ]);
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 3000.00,
            'status' => FinancialStatus::CANCELLED,
            'due_date' => now()->addWeek(),
        ]);

        $response = $this->getJson('/api/v1/financial/consolidated');

        $response->assertOk();
        $totals = $response->json('data.totals');
        $this->assertEquals(0, $totals['receivables_open']);
        $this->assertEquals(0, $totals['payables_open']);
    }

    public function test_consolidated_excludes_renegotiated_receivables_and_payables(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1800.00,
            'amount_paid' => 300.00,
            'status' => FinancialStatus::RENEGOTIATED,
            'due_date' => now()->subDays(15),
        ]);

        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 900.00,
            'amount_paid' => 100.00,
            'status' => FinancialStatus::RENEGOTIATED,
            'due_date' => now()->subDays(10),
        ]);

        $response = $this->getJson('/api/v1/financial/consolidated')->assertOk();

        $totals = $response->json('data.totals');
        $this->assertEquals(0, $totals['receivables_open']);
        $this->assertEquals(0, $totals['receivables_overdue']);
        $this->assertEquals(0, $totals['payables_open']);
        $this->assertEquals(0, $totals['payables_overdue']);
    }

    public function test_consolidated_calculates_balance(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 3000.00,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING,
            'due_date' => now()->addWeek(),
        ]);
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'status' => FinancialStatus::PENDING,
            'due_date' => now()->addWeek(),
        ]);

        $response = $this->getJson('/api/v1/financial/consolidated');

        $response->assertOk();
        // balance = receivables_open - payables_open = 3000 - 1000 = 2000
        $this->assertEquals(2000.0, $response->json('data.balance'));
    }

    public function test_consolidated_filters_by_tenant_id(): void
    {
        $tenant2 = Tenant::factory()->create();
        $this->user->tenants()->syncWithoutDetaching([$tenant2->id]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING,
            'due_date' => now()->addWeek(),
        ]);

        $customer2 = Customer::factory()->create(['tenant_id' => $tenant2->id]);
        AccountReceivable::factory()->create([
            'tenant_id' => $tenant2->id,
            'customer_id' => $customer2->id,
            'created_by' => $this->user->id,
            'amount' => 2000.00,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING,
            'due_date' => now()->addWeek(),
        ]);

        // Filter by tenant 1 only
        $response = $this->getJson("/api/v1/financial/consolidated?tenant_id={$this->tenant->id}");

        $response->assertOk();
        $perTenant = $response->json('data.per_tenant');
        $this->assertCount(1, $perTenant);
        $this->assertEquals($this->tenant->id, $perTenant[0]['tenant_id']);
    }

    public function test_consolidated_shows_period_as_current_month(): void
    {
        $response = $this->getJson('/api/v1/financial/consolidated');

        $response->assertOk()
            ->assertJsonPath('data.period', Carbon::today()->format('Y-m'));
    }

    public function test_consolidated_uses_payment_date_and_open_balance(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
            'amount' => 1000.00,
            'amount_paid' => 1000.00,
            'status' => FinancialStatus::PAID,
            'due_date' => now()->subMonths(2),
            'paid_at' => now()->subMonths(2),
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'received_by' => $this->user->id,
            'amount' => 1000.00,
            'payment_date' => now()->toDateString(),
        ]);

        $payable = AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'amount' => 900.00,
            'amount_paid' => 0.00,
            'status' => FinancialStatus::PENDING,
            'due_date' => now()->subDays(10),
            'paid_at' => now()->subMonths(2),
        ]);

        Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountPayable::class,
            'payable_id' => $payable->id,
            'received_by' => $this->user->id,
            'amount' => 300.00,
            'payment_date' => now()->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/financial/consolidated')->assertOk();

        $totals = $response->json('data.totals');
        $this->assertEquals(1000.0, $totals['received_month']);
        $this->assertEquals(300.0, $totals['paid_month']);
        $this->assertEquals(600.0, $totals['payables_open']);
        $this->assertEquals(600.0, $totals['payables_overdue']);
    }

    // ── AUTH ──────────────────────────────────────────────────

    public function test_unauthenticated_returns_401(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/financial/consolidated');

        $response->assertStatus(401);
    }
}
