<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MiscControllersTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    }

    // ── Agenda ──

    public function test_agenda_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/agenda');
        $response->assertOk();
    }

    public function test_agenda_store(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/agenda', [
            'title' => 'Calibrar balança',
            'type' => 'tarefa',
        ]);

        $response->assertCreated();
    }

    public function test_agenda_resumo(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/agenda/resumo');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['hoje', 'atrasadas', 'sem_prazo', 'total_aberto', 'abertas', 'urgentes', 'seguindo'],
            ]);
    }

    // ── Expenses ──

    public function test_expenses_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/expenses');
        $response->assertOk();
    }

    public function test_expenses_store(): void
    {
        $cat = ExpenseCategory::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/expenses', [
            'description' => 'Combustível',
            'amount' => '200.00',
            'expense_date' => now()->format('Y-m-d'),
            'expense_category_id' => $cat->id,
        ]);

        $response->assertCreated();
    }

    // ── Products ──

    public function test_products_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/products');
        $response->assertOk();
    }

    public function test_products_store(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/products', [
            'name' => 'Peso padrão 1kg',
            'sku' => 'PP-001',
            'type' => 'product',
        ]);

        $response->assertCreated();
    }

    // ── Service Calls ──

    public function test_service_calls_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/service-calls');
        $response->assertOk();
    }

    public function test_service_calls_store(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/service-calls', [
            'customer_id' => $this->customer->id,
            'subject' => 'Balança com defeito',
            'description' => 'Balança não está zerando',
            'priority' => 'medium',
        ]);

        $response->assertCreated();
    }

    // ── Unauthenticated ──

    public function test_agenda_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/agenda');
        $response->assertUnauthorized();
    }

    public function test_expenses_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/expenses');
        $response->assertUnauthorized();
    }

    public function test_products_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/products');
        $response->assertUnauthorized();
    }

    public function test_service_calls_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/service-calls');
        $response->assertUnauthorized();
    }
}
