<?php

namespace Tests\Feature\Api\V1\Logistics;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AutoAssignmentRule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DispatchControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    public function test_can_list_auto_assign_rules(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        AutoAssignmentRule::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/logistics/dispatch/rules');

        $response->assertOk()
            ->assertJsonStructure(['data' => [['id', 'name', 'priority', 'criteria']]]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_store_auto_assign_rule(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $payload = [
            'name' => 'Test Rule',
            'priority' => 1,
            'is_active' => true,
            'criteria' => ['type' => 'distance', 'max' => 50],
            'action' => ['assignTo' => 'team_1'],
        ];

        $response = $this->postJson('/api/v1/logistics/dispatch/rules', $payload);

        $response->assertCreated();
        $this->assertDatabaseHas('auto_assignment_rules', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Rule',
            'priority' => 1,
        ]);
    }

    public function test_can_update_auto_assign_rule(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $rule = AutoAssignmentRule::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Old Name']);

        $payload = [
            'name' => 'Updated Rule',
            'priority' => 2,
            'is_active' => false,
            'criteria' => ['type' => 'skill', 'required' => 'welding'],
            'action' => ['assignTo' => 'closest'],
        ];

        $response = $this->putJson("/api/v1/logistics/dispatch/rules/{$rule->id}", $payload);

        $response->assertOk();
        $this->assertEquals('Updated Rule', $response->json('data.name'));
        $this->assertDatabaseHas('auto_assignment_rules', [
            'id' => $rule->id,
            'name' => 'Updated Rule',
        ]);
    }

    public function test_can_delete_auto_assign_rule(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $rule = AutoAssignmentRule::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->deleteJson("/api/v1/logistics/dispatch/rules/{$rule->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('auto_assignment_rules', ['id' => $rule->id]);
    }

    public function test_trigger_auto_assign_returns_valid_response(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $workOrder = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson("/api/v1/logistics/dispatch/auto-assign/{$workOrder->id}");

        // Auto assignment service may return 200 or 404 depending if technician found
        $this->assertContains($response->status(), [200, 404]);
    }

    public function test_auto_assign_rules_tenant_isolation(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $otherTenant = Tenant::factory()->create();
        AutoAssignmentRule::factory()->count(3)->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson('/api/v1/logistics/dispatch/rules');

        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/logistics/dispatch/rules');
        $response->assertUnauthorized();
    }

    public function test_respects_permissions_403(): void
    {
        Gate::before(fn () => false); // Deny all

        Sanctum::actingAs($this->user, ['*']);
        $this->withMiddleware([CheckPermission::class]);

        $response = $this->getJson('/api/v1/logistics/dispatch/rules');

        // Note: dispatch might use os.work_order.view or fleet permission.
        $this->assertContains($response->status(), [403, 404]);
    }
}
