<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountPayableCategory;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsolidatedFinancialControllerTest extends TestCase
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

    public function test_index_returns_200_with_no_data(): void
    {
        $response = $this->getJson('/api/v1/financial/consolidated');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['period', 'totals', 'balance', 'per_tenant']]);
    }

    public function test_index_aggregates_current_tenant_open_receivables_and_payables(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING->value,
            'due_date' => now()->addDays(30),
        ]);

        $category = AccountPayableCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        AccountPayable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'category_id' => $category->id,
            'amount' => 400,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING->value,
            'due_date' => now()->addDays(30),
        ]);

        $response = $this->getJson('/api/v1/financial/consolidated');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertEquals('1000.00', $data['totals']['receivables_open']);
        $this->assertEquals('400.00', $data['totals']['payables_open']);
        // balance = receivables_open - payables_open = 600
        $this->assertEquals('600.00', $data['balance']);
    }

    public function test_index_excludes_other_tenants_data(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        AccountReceivable::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'amount' => 999999,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING->value,
            'due_date' => now()->addDays(30),
        ]);

        $response = $this->getJson('/api/v1/financial/consolidated');

        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotEquals('999999.00', $data['totals']['receivables_open'], 'Dados de outro tenant vazaram na consolidação');
        $this->assertEquals('0.00', $data['totals']['receivables_open']);
    }

    public function test_index_ignores_tenant_filter_when_not_in_user_scope(): void
    {
        // Filtro tenant_id para tenant que o user não tem acesso — deve ser IGNORADO
        $foreignTenantId = Tenant::factory()->create()->id;

        $response = $this->getJson("/api/v1/financial/consolidated?tenant_id={$foreignTenantId}");

        $response->assertOk();
        $data = $response->json('data');

        // per_tenant deve conter apenas o tenant atual
        $tenantIds = collect($data['per_tenant'])->pluck('tenant_id')->all();
        $this->assertContains($this->tenant->id, $tenantIds);
        $this->assertNotContains($foreignTenantId, $tenantIds);
    }

    public function test_index_rejects_invalid_per_page(): void
    {
        $response = $this->getJson('/api/v1/financial/consolidated?per_page=9999');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }
}
