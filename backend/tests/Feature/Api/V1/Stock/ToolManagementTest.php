<?php

namespace Tests\Feature\Api\V1\Stock;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ToolManagementTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

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
        $this->otherTenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createTool(array $overrides = []): int
    {
        return DB::table('tool_inventories')->insertGetId(array_merge([
            'name' => 'Chave de Torque T50',
            'serial_number' => 'SN-'.uniqid(),
            'status' => 'available',
            'tenant_id' => $this->tenant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function createCalibration(int $toolId, array $overrides = []): int
    {
        return DB::table('tool_calibrations')->insertGetId(array_merge([
            'tool_inventory_id' => $toolId,
            'calibration_date' => '2026-01-15',
            'next_due_date' => '2027-01-15',
            'certificate_number' => 'CERT-'.uniqid(),
            'laboratory' => 'Lab Acme',
            'result' => 'approved',
            'tenant_id' => $this->tenant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════
    // Tool Inventory CRUD
    // ═══════════════════════════════════════════════════════════

    public function test_inventory_index_returns_paginated_tools(): void
    {
        $this->createTool(['name' => 'Alicate A']);
        $this->createTool(['name' => 'Broca B']);

        $response = $this->getJson('/api/v1/tool-inventories');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
            ]);
        $this->assertGreaterThanOrEqual(2, $response->json('total'));
    }

    public function test_inventory_index_filters_by_search(): void
    {
        $this->createTool(['name' => 'Multimetro Digital']);
        $this->createTool(['name' => 'Torquimetro']);

        $response = $this->getJson('/api/v1/tool-inventories?search=Multimetro');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Multimetro Digital'));
        $this->assertFalse($names->contains('Torquimetro'));
    }

    public function test_inventory_index_filters_by_status(): void
    {
        $this->createTool(['name' => 'Tool Available', 'status' => 'available']);
        $this->createTool(['name' => 'Tool Retired', 'status' => 'retired']);

        $response = $this->getJson('/api/v1/tool-inventories?status=retired');

        $response->assertOk();
        $statuses = collect($response->json('data'))->pluck('status')->unique()->all();
        $this->assertEquals(['retired'], $statuses);
    }

    public function test_inventory_index_filters_by_user_id(): void
    {
        $this->createTool(['name' => 'Tool Assigned', 'assigned_to' => $this->user->id]);
        $this->createTool(['name' => 'Tool Unassigned', 'assigned_to' => null]);

        $response = $this->getJson('/api/v1/tool-inventories?user_id='.$this->user->id);

        $response->assertOk();
        foreach ($response->json('data') as $tool) {
            $this->assertEquals($this->user->id, $tool['assigned_to']);
        }
    }

    public function test_inventory_store_creates_tool(): void
    {
        $payload = [
            'name' => 'Manometro Digital',
            'serial_number' => 'MN-2026-001',
            'status' => 'available',
            'purchase_value' => 350.50,
        ];

        $response = $this->postJson('/api/v1/tool-inventories', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Manometro Digital')
            ->assertJsonPath('data.serial_number', 'MN-2026-001');

        $this->assertDatabaseHas('tool_inventories', [
            'name' => 'Manometro Digital',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_inventory_store_defaults_status_to_available(): void
    {
        $response = $this->postJson('/api/v1/tool-inventories', [
            'name' => 'Ferramenta Sem Status',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'available');
    }

    public function test_inventory_store_validates_required_name(): void
    {
        $response = $this->postJson('/api/v1/tool-inventories', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_inventory_store_validates_invalid_status(): void
    {
        $response = $this->postJson('/api/v1/tool-inventories', [
            'name' => 'Tool',
            'status' => 'broken',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_inventory_update_modifies_tool(): void
    {
        $toolId = $this->createTool(['name' => 'Old Name', 'status' => 'available']);

        $response = $this->putJson("/api/v1/tool-inventories/{$toolId}", [
            'name' => 'New Name',
            'status' => 'in_use',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'New Name')
            ->assertJsonPath('data.status', 'in_use');
    }

    public function test_inventory_update_returns_404_for_other_tenant(): void
    {
        $toolId = $this->createTool(['tenant_id' => $this->otherTenant->id]);

        $response = $this->putJson("/api/v1/tool-inventories/{$toolId}", [
            'name' => 'Hacked',
        ]);

        $response->assertNotFound();
    }

    public function test_inventory_destroy_deletes_tool(): void
    {
        $toolId = $this->createTool();

        $response = $this->deleteJson("/api/v1/tool-inventories/{$toolId}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('tool_inventories', ['id' => $toolId]);
    }

    public function test_inventory_destroy_returns_404_for_other_tenant(): void
    {
        $toolId = $this->createTool(['tenant_id' => $this->otherTenant->id]);

        $response = $this->deleteJson("/api/v1/tool-inventories/{$toolId}");

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    // Tool Calibration CRUD
    // ═══════════════════════════════════════════════════════════

    public function test_calibration_index_returns_paginated_list(): void
    {
        $toolId = $this->createTool();
        $this->createCalibration($toolId);
        $this->createCalibration($toolId, ['calibration_date' => '2026-06-01']);

        $response = $this->getJson('/api/v1/tool-calibrations');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'total',
            ]);
        $this->assertGreaterThanOrEqual(2, $response->json('total'));
    }

    public function test_calibration_index_includes_tool_name(): void
    {
        $toolId = $this->createTool(['name' => 'Paquimetro Digital']);
        $this->createCalibration($toolId);

        $response = $this->getJson('/api/v1/tool-calibrations');

        $response->assertOk();
        $first = collect($response->json('data'))->firstWhere('tool_inventory_id', $toolId);
        $this->assertNotNull($first);
        $this->assertEquals('Paquimetro Digital', $first['tool_name']);
    }

    public function test_calibration_index_filters_by_tool_id(): void
    {
        $tool1 = $this->createTool(['name' => 'Tool A']);
        $tool2 = $this->createTool(['name' => 'Tool B']);
        $this->createCalibration($tool1);
        $this->createCalibration($tool2);

        $response = $this->getJson("/api/v1/tool-calibrations?tool_id={$tool1}");

        $response->assertOk();
        foreach ($response->json('data') as $cal) {
            $this->assertEquals($tool1, $cal['tool_inventory_id']);
        }
    }

    public function test_calibration_index_filters_by_status(): void
    {
        $toolId = $this->createTool();
        $this->createCalibration($toolId, ['result' => 'approved']);
        $this->createCalibration($toolId, ['result' => 'rejected']);

        $response = $this->getJson('/api/v1/tool-calibrations?status=rejected');

        $response->assertOk();
        foreach ($response->json('data') as $cal) {
            $this->assertEquals('rejected', $cal['result']);
        }
    }

    public function test_calibration_store_creates_calibration(): void
    {
        $toolId = $this->createTool();

        $payload = [
            'tool_inventory_id' => $toolId,
            'calibration_date' => '2026-03-01',
            'next_calibration_date' => '2027-03-01',
            'certificate_number' => 'CERT-001',
            'performed_by' => 'Lab Calibra',
            'result' => 'approved',
            'notes' => 'Aprovado sem ressalvas',
        ];

        $response = $this->postJson('/api/v1/tool-calibrations', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.tool_inventory_id', $toolId)
            ->assertJsonPath('data.certificate_number', 'CERT-001');

        $this->assertDatabaseHas('tool_calibrations', [
            'tool_inventory_id' => $toolId,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_calibration_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/tool-calibrations', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tool_inventory_id', 'calibration_date']);
    }

    public function test_calibration_store_validates_next_date_after_calibration_date(): void
    {
        $toolId = $this->createTool();

        $response = $this->postJson('/api/v1/tool-calibrations', [
            'tool_inventory_id' => $toolId,
            'calibration_date' => '2026-06-01',
            'next_calibration_date' => '2026-01-01',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['next_calibration_date']);
    }

    public function test_calibration_update_modifies_calibration(): void
    {
        $toolId = $this->createTool();
        $calId = $this->createCalibration($toolId, ['certificate_number' => 'OLD-CERT']);

        $response = $this->putJson("/api/v1/tool-calibrations/{$calId}", [
            'certificate_number' => 'NEW-CERT',
            'result' => 'rejected',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.certificate_number', 'NEW-CERT');
    }

    public function test_calibration_update_returns_404_for_other_tenant(): void
    {
        $toolId = $this->createTool(['tenant_id' => $this->otherTenant->id]);
        $calId = $this->createCalibration($toolId, ['tenant_id' => $this->otherTenant->id]);

        $response = $this->putJson("/api/v1/tool-calibrations/{$calId}", [
            'certificate_number' => 'HACK',
        ]);

        $response->assertNotFound();
    }

    public function test_calibration_destroy_deletes_calibration(): void
    {
        $toolId = $this->createTool();
        $calId = $this->createCalibration($toolId);

        $response = $this->deleteJson("/api/v1/tool-calibrations/{$calId}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('tool_calibrations', ['id' => $calId]);
    }

    public function test_calibration_destroy_returns_404_for_other_tenant(): void
    {
        $toolId = $this->createTool(['tenant_id' => $this->otherTenant->id]);
        $calId = $this->createCalibration($toolId, ['tenant_id' => $this->otherTenant->id]);

        $response = $this->deleteJson("/api/v1/tool-calibrations/{$calId}");

        $response->assertNotFound();
    }

    // ═══════════════════════════════════════════════════════════
    // Tenant isolation
    // ═══════════════════════════════════════════════════════════

    public function test_inventory_index_does_not_leak_other_tenant_tools(): void
    {
        $this->createTool(['name' => 'My Tool']);
        $this->createTool(['name' => 'Other Tool', 'tenant_id' => $this->otherTenant->id]);

        $response = $this->getJson('/api/v1/tool-inventories');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('My Tool'));
        $this->assertFalse($names->contains('Other Tool'));
    }

    public function test_calibration_index_does_not_leak_other_tenant_data(): void
    {
        $myTool = $this->createTool();
        $this->createCalibration($myTool, ['certificate_number' => 'MY-CERT']);

        $otherTool = $this->createTool(['tenant_id' => $this->otherTenant->id]);
        $this->createCalibration($otherTool, [
            'tenant_id' => $this->otherTenant->id,
            'certificate_number' => 'OTHER-CERT',
        ]);

        $response = $this->getJson('/api/v1/tool-calibrations');

        $response->assertOk();
        $certs = collect($response->json('data'))->pluck('certificate_number');
        $this->assertTrue($certs->contains('MY-CERT'));
        $this->assertFalse($certs->contains('OTHER-CERT'));
    }
}
