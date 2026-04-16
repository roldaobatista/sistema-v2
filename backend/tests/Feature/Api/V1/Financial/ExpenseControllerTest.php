<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpenseControllerTest extends TestCase
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

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_only_current_tenant_expenses(): void
    {
        Expense::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        Expense::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/expenses');

        $response->assertOk()->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertIsArray($data);

        foreach ($data as $expense) {
            $this->assertEquals(
                $this->tenant->id,
                $expense['tenant_id'] ?? null,
                'Expense de outro tenant vazou na listagem'
            );
        }
    }

    public function test_index_filters_my_own_expenses_only(): void
    {
        // Minhas despesas
        Expense::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        // Despesas de OUTRO usuario do MESMO tenant
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        Expense::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/expenses?my=1');

        $response->assertOk();
        $data = $response->json('data');

        foreach ($data as $expense) {
            $this->assertEquals(
                $this->user->id,
                $expense['created_by'] ?? null,
                'Filtro my=1 vazou expense de outro usuario'
            );
        }
    }

    public function test_show_returns_404_for_cross_tenant_expense(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $foreign = Expense::factory()->create([
            'tenant_id' => $otherTenant->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/v1/expenses/{$foreign->id}");

        $response->assertStatus(404);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/expenses', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'description',
                'amount',
                'expense_date',
            ]);
    }

    public function test_store_rejects_amount_below_minimum(): void
    {
        $response = $this->postJson('/api/v1/expenses', [
            'description' => 'Teste min',
            'amount' => 0, // min:0.01
            'expense_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }
}
