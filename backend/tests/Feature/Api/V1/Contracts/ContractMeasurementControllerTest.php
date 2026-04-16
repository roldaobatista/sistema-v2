<?php

namespace Tests\Feature\Api\V1\Contracts;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Contract;
use App\Models\ContractMeasurement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContractMeasurementControllerTest extends TestCase
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
            'is_active' => true,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_can_list_measurements_with_pagination(): void
    {
        ContractMeasurement::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/contracts/measurements');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonCount(3, 'data');
    }

    public function test_can_create_measurement(): void
    {
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/contracts/measurements', [
            'contract_id' => $contract->id,
            'period' => '2026-04',
            'total_accepted' => 5000.00,
            'total_rejected' => 200.00,
            'status' => 'pending_approval',
            'notes' => 'Measurement for April',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('contract_measurements', [
            'contract_id' => $contract->id,
            'period' => '2026-04',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_can_show_measurement(): void
    {
        $measurement = ContractMeasurement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/contracts/measurements/{$measurement->id}");

        $response->assertOk()
            ->assertJsonStructure(['id', 'contract_id', 'period', 'total_accepted', 'total_rejected', 'contract']);
    }

    public function test_can_update_measurement(): void
    {
        $measurement = ContractMeasurement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->putJson("/api/v1/contracts/measurements/{$measurement->id}", [
            'status' => 'approved',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('contract_measurements', [
            'id' => $measurement->id,
            'status' => 'approved',
        ]);
    }

    public function test_can_delete_measurement(): void
    {
        $measurement = ContractMeasurement::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/v1/contracts/measurements/{$measurement->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('contract_measurements', ['id' => $measurement->id]);
    }

    public function test_store_fails_with_missing_required_fields(): void
    {
        $response = $this->postJson('/api/v1/contracts/measurements', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['contract_id', 'period', 'total_accepted', 'total_rejected']);
    }

    public function test_store_fails_with_invalid_data(): void
    {
        $response = $this->postJson('/api/v1/contracts/measurements', [
            'contract_id' => 999999,
            'period' => '',
            'total_accepted' => 'not-a-number',
            'total_rejected' => 'not-a-number',
        ]);

        $response->assertUnprocessable();
    }

    public function test_cannot_access_measurement_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $measurement = ContractMeasurement::factory()->create([
            'tenant_id' => $otherTenant->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/v1/contracts/measurements/{$measurement->id}");

        $response->assertNotFound();
    }

    public function test_only_lists_measurements_from_own_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        // Create measurements for other tenant
        ContractMeasurement::factory()->count(2)->create([
            'tenant_id' => $otherTenant->id,
            'created_by' => $otherUser->id,
        ]);

        // Create measurements for own tenant
        ContractMeasurement::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/contracts/measurements');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_store_assigns_tenant_id_and_created_by_automatically(): void
    {
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/contracts/measurements', [
            'contract_id' => $contract->id,
            'period' => '2026-05',
            'total_accepted' => 1000.00,
            'total_rejected' => 50.00,
        ]);

        $response->assertCreated();

        $measurement = ContractMeasurement::latest('id')->first();
        $this->assertEquals($this->tenant->id, $measurement->tenant_id);
        $this->assertEquals($this->user->id, $measurement->created_by);
    }

    public function test_pagination_respects_per_page_parameter(): void
    {
        ContractMeasurement::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/contracts/measurements?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_per_page_is_capped_at_100(): void
    {
        $response = $this->getJson('/api/v1/contracts/measurements?per_page=500');

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_created_by_cannot_be_set_via_request(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/contracts/measurements', [
            'contract_id' => $contract->id,
            'period' => '2026-06',
            'total_accepted' => 2000.00,
            'total_rejected' => 0.00,
            'created_by' => $otherUser->id, // Attempt to set created_by
        ]);

        $response->assertCreated();

        $measurement = ContractMeasurement::latest('id')->first();
        // created_by should be the authenticated user, not the one sent in the request
        $this->assertEquals($this->user->id, $measurement->created_by);
    }

    public function test_can_create_measurement_with_items(): void
    {
        $contract = Contract::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/contracts/measurements', [
            'contract_id' => $contract->id,
            'period' => '2026-07',
            'items' => [
                ['description' => 'Foundation work', 'quantity' => 10, 'unit_price' => 500.00, 'accepted' => true],
                ['description' => 'Electrical wiring', 'quantity' => 5, 'unit_price' => 200.00, 'accepted' => false],
            ],
            'total_accepted' => 5000.00,
            'total_rejected' => 1000.00,
        ]);

        $response->assertCreated();

        $measurement = ContractMeasurement::latest('id')->first();
        $this->assertCount(2, $measurement->items);
        $this->assertEquals('Foundation work', $measurement->items[0]['description']);
    }
}
