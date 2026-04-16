<?php

namespace Tests\Feature\Api\V1;

use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CashFlowControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_cash_flow_returns_200_with_empty_tenant(): void
    {
        $response = $this->getJson('/api/v1/cash-flow');

        $response->assertOk();
    }

    public function test_cash_flow_rejects_months_above_36(): void
    {
        $response = $this->getJson('/api/v1/cash-flow?months=99');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['months']);
    }

    public function test_cash_flow_rejects_months_below_1(): void
    {
        $response = $this->getJson('/api/v1/cash-flow?months=0');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['months']);
    }

    public function test_cash_flow_rejects_date_to_before_date_from(): void
    {
        $response = $this->getJson('/api/v1/cash-flow?date_from=2026-03-01&date_to=2026-01-01');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date_to']);
    }

    public function test_cash_flow_excludes_other_tenant_data(): void
    {
        // Cria receivable no tenant atual
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'amount' => 1000,
            'amount_paid' => 1000,
            'status' => FinancialStatus::PAID->value,
            'paid_at' => now(),
            'due_date' => now(),
        ]);

        // Cria receivable em outro tenant (valor alto para ser detectado se vazar)
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        AccountReceivable::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'amount' => 999999,
            'amount_paid' => 999999,
            'status' => FinancialStatus::PAID->value,
            'paid_at' => now(),
            'due_date' => now(),
        ]);

        $response = $this->getJson('/api/v1/cash-flow?months=1');

        $response->assertOk();
        $body = $response->json();

        // O valor obscenamente alto do outro tenant NÃO pode aparecer em lugar nenhum
        $json = json_encode($body);
        $this->assertStringNotContainsString('999999', $json, 'CashFlow vazou dados de outro tenant');
    }

    public function test_dre_returns_200(): void
    {
        $response = $this->getJson('/api/v1/dre?date_from='.now()->startOfMonth()->format('Y-m-d').'&date_to='.now()->endOfMonth()->format('Y-m-d'));

        $response->assertOk();
    }

    public function test_dre_rejects_date_from_after_date_to_via_business_rule(): void
    {
        // Validação de DRE pode ser em rules ou business rule; ambos retornam 422
        $response = $this->getJson('/api/v1/dre?date_from=2026-12-31&date_to=2026-01-01');

        $this->assertContains($response->status(), [422]);
    }
}
