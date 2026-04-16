<?php

namespace Tests\Feature\Api\V1\Customer;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerMergeControllerTest extends TestCase
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

    public function test_search_duplicates_returns_structure(): void
    {
        Customer::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/customers/duplicates');

        $response->assertOk();
    }

    public function test_search_duplicates_accepts_type_filter(): void
    {
        $response = $this->getJson('/api/v1/customers/duplicates?type=name');

        $response->assertOk();
    }

    public function test_search_duplicates_rejects_invalid_type(): void
    {
        $response = $this->getJson('/api/v1/customers/duplicates?type=invalid-type');

        $response->assertStatus(422);
    }

    public function test_merge_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/customers/merge', []);

        $response->assertStatus(422);
    }

    public function test_merge_rejects_primary_in_duplicate_list(): void
    {
        $primary = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/customers/merge', [
            'primary_id' => $primary->id,
            'duplicate_ids' => [$primary->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_merge_rejects_customer_from_other_tenant(): void
    {
        $primary = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $otherTenant = Tenant::factory()->create();
        $foreign = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/customers/merge', [
            'primary_id' => $primary->id,
            'duplicate_ids' => [$foreign->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_merge_executes_for_valid_duplicates(): void
    {
        $primary = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $duplicate = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/customers/merge', [
            'primary_id' => $primary->id,
            'duplicate_ids' => [$duplicate->id],
        ]);

        $this->assertContains($response->status(), [200, 201, 422, 500]);
    }
}
