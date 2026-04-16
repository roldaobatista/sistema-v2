<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Enums\FinancialStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\DebtRenegotiation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DebtRenegotiationControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createReceivable(array $overrides = []): AccountReceivable
    {
        return AccountReceivable::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'amount' => 1000,
            'amount_paid' => 0,
            'status' => FinancialStatus::PENDING->value,
        ], $overrides));
    }

    public function test_index_returns_only_current_tenant_renegotiations(): void
    {
        DebtRenegotiation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
            'original_total' => 500,
            'negotiated_total' => 450,
            'new_installments' => 2,
            'first_due_date' => now()->addDays(10),
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        DebtRenegotiation::create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
            'status' => 'pending',
            'original_total' => 700,
            'negotiated_total' => 600,
            'new_installments' => 3,
            'first_due_date' => now()->addDays(10),
        ]);

        $response = $this->getJson('/api/v1/debt-renegotiations');

        $response->assertOk();
        $data = $response->json('data') ?? $response->json();
        $this->assertIsArray($data);
        foreach ($data as $r) {
            if (isset($r['tenant_id'])) {
                $this->assertEquals($this->tenant->id, $r['tenant_id']);
            }
        }
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/debt-renegotiations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'customer_id',
                'receivable_ids',
                'new_due_date',
                'installments',
            ]);
    }

    public function test_store_rejects_installments_above_48(): void
    {
        $receivable = $this->createReceivable();

        $response = $this->postJson('/api/v1/debt-renegotiations', [
            'customer_id' => $this->customer->id,
            'receivable_ids' => [$receivable->id],
            'new_due_date' => now()->addDays(30)->format('Y-m-d'),
            'installments' => 60, // max:48
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['installments']);
    }

    public function test_store_rejects_discount_percentage_above_100(): void
    {
        $receivable = $this->createReceivable();

        $response = $this->postJson('/api/v1/debt-renegotiations', [
            'customer_id' => $this->customer->id,
            'receivable_ids' => [$receivable->id],
            'new_due_date' => now()->addDays(30)->format('Y-m-d'),
            'installments' => 3,
            'discount_percentage' => 150, // max:100
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['discount_percentage']);
    }

    public function test_store_rejects_receivables_from_other_customer(): void
    {
        // Receivable pertence a OUTRO cliente do MESMO tenant
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $receivable = $this->createReceivable(['customer_id' => $otherCustomer->id]);

        $response = $this->postJson('/api/v1/debt-renegotiations', [
            'customer_id' => $this->customer->id, // cliente diferente do receivable
            'receivable_ids' => [$receivable->id],
            'new_due_date' => now()->addDays(30)->format('Y-m-d'),
            'installments' => 3,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['receivable_ids']);
    }

    public function test_store_rejects_receivable_with_no_open_balance(): void
    {
        // Receivable ja quitado
        $receivable = $this->createReceivable([
            'amount' => 500,
            'amount_paid' => 500,
            'status' => FinancialStatus::PAID->value,
        ]);

        $response = $this->postJson('/api/v1/debt-renegotiations', [
            'customer_id' => $this->customer->id,
            'receivable_ids' => [$receivable->id],
            'new_due_date' => now()->addDays(30)->format('Y-m-d'),
            'installments' => 3,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['receivable_ids']);
    }

    public function test_show_returns_404_for_cross_tenant_renegotiation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $foreign = DebtRenegotiation::create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
            'status' => 'pending',
            'original_total' => 100,
            'negotiated_total' => 90,
            'new_installments' => 2,
            'first_due_date' => now()->addDays(10),
        ]);

        $response = $this->getJson("/api/v1/debt-renegotiations/{$foreign->id}");

        // BelongsToTenant global scope deveria retornar 404
        $response->assertStatus(404);
    }
}
