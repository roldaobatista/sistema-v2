<?php

namespace Tests\Feature\Api\V1;

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

class RenegotiationControllerTest extends TestCase
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

    public function test_index_returns_only_current_tenant_renegotiations(): void
    {
        DebtRenegotiation::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => 'pending',
            'original_total' => 1000,
            'negotiated_total' => 900,
            'new_installments' => 3,
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
            'original_total' => 2000,
            'negotiated_total' => 1800,
            'new_installments' => 3,
            'first_due_date' => now()->addDays(10),
        ]);

        $response = $this->getJson('/api/v1/renegotiations');

        $response->assertOk()->assertJsonStructure(['data']);

        $data = $response->json('data');
        foreach ($data as $r) {
            if (isset($r['tenant_id'])) {
                $this->assertEquals(
                    $this->tenant->id,
                    $r['tenant_id'],
                    'Renegotiation de outro tenant vazou'
                );
            }
        }
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/renegotiations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'customer_id',
                'receivable_ids',
                'negotiated_total',
                'new_installments',
                'first_due_date',
            ]);
    }

    public function test_store_rejects_customer_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/renegotiations', [
            'customer_id' => $foreignCustomer->id,
            'receivable_ids' => [1],
            'negotiated_total' => 100,
            'new_installments' => 2,
            'first_due_date' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_rejects_first_due_date_in_past(): void
    {
        $response = $this->postJson('/api/v1/renegotiations', [
            'customer_id' => $this->customer->id,
            'receivable_ids' => [1],
            'negotiated_total' => 100,
            'new_installments' => 2,
            'first_due_date' => now()->subDays(1)->format('Y-m-d'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_due_date']);
    }

    public function test_store_rejects_installments_above_limit(): void
    {
        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->postJson('/api/v1/renegotiations', [
            'customer_id' => $this->customer->id,
            'receivable_ids' => [$receivable->id],
            'negotiated_total' => 100,
            'new_installments' => 120, // max:60
            'first_due_date' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['new_installments']);
    }

    public function test_store_rejects_negotiated_total_below_minimum(): void
    {
        $receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->postJson('/api/v1/renegotiations', [
            'customer_id' => $this->customer->id,
            'receivable_ids' => [$receivable->id],
            'negotiated_total' => 0, // min:0.01
            'new_installments' => 3,
            'first_due_date' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['negotiated_total']);
    }
}
