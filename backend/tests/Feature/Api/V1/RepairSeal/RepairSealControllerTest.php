<?php

namespace Tests\Feature\Api\V1\RepairSeal;

use App\Events\RepairSeal\SealUsedOnWorkOrder;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Equipment;
use App\Models\InmetroSeal;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RepairSealControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->admin, ['*']);
    }

    // ── Index ──────────────────────────────────────────────────

    public function test_index_returns_paginated_seals(): void
    {
        InmetroSeal::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/repair-seals');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_filters_by_type(): void
    {
        InmetroSeal::factory()->seloReparo()->create(['tenant_id' => $this->tenant->id]);
        InmetroSeal::factory()->lacre()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/repair-seals?type=seal_reparo');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $seal) {
            $this->assertEquals('seal_reparo', $seal['type']);
        }
    }

    public function test_index_filters_by_status(): void
    {
        InmetroSeal::factory()->available()->create(['tenant_id' => $this->tenant->id]);
        InmetroSeal::factory()->assigned($this->technician->id)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/repair-seals?status=assigned');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $seal) {
            $this->assertEquals('assigned', $seal['status']);
        }
    }

    public function test_index_filters_by_technician(): void
    {
        $sealForTech = InmetroSeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => InmetroSeal::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);
        InmetroSeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->admin->id,
            'status' => InmetroSeal::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/repair-seals?technician_id='.$this->technician->id);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($sealForTech->id, $data[0]['id']);
    }

    // ── Show ───────────────────────────────────────────────────

    public function test_show_returns_seal_details(): void
    {
        $seal = InmetroSeal::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson("/api/v1/repair-seals/{$seal->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $seal->id)
            ->assertJsonPath('data.number', $seal->number);
    }

    public function test_show_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $seal = InmetroSeal::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson("/api/v1/repair-seals/{$seal->id}");

        $response->assertStatus(404);
    }

    // ── Dashboard ──────────────────────────────────────────────

    public function test_dashboard_returns_stats(): void
    {
        InmetroSeal::factory()->count(3)->available()->create(['tenant_id' => $this->tenant->id]);
        InmetroSeal::factory()->assigned($this->technician->id)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/repair-seals/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [
                'total_available',
                'total_assigned',
                'total_pending_psei',
                'total_overdue',
                'by_type',
                'technician_summary',
            ]]);
    }

    // ── My Inventory ───────────────────────────────────────────

    public function test_my_inventory_returns_technician_seals(): void
    {
        Sanctum::actingAs($this->technician, ['*']);

        InmetroSeal::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => InmetroSeal::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        // Seal assigned to someone else
        InmetroSeal::factory()->assigned($this->admin->id)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/repair-seals/my-inventory');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    // ── Assign ─────────────────────────────────────────────────

    public function test_assign_seals_to_technician(): void
    {
        $seals = InmetroSeal::factory()->count(3)->available()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/repair-seals/assign', [
            'seal_ids' => $seals->pluck('id')->toArray(),
            'technician_id' => $this->technician->id,
        ]);

        $response->assertStatus(200);

        foreach ($seals as $seal) {
            $this->assertDatabaseHas('inmetro_seals', [
                'id' => $seal->id,
                'status' => 'assigned',
                'assigned_to' => $this->technician->id,
            ]);
        }

        // Verify assignment audit log
        $this->assertDatabaseHas('repair_seal_assignments', [
            'seal_id' => $seals->first()->id,
            'technician_id' => $this->technician->id,
            'action' => 'assigned',
        ]);
    }

    public function test_assign_rejects_unavailable_seals(): void
    {
        $seal = InmetroSeal::factory()->assigned($this->admin->id)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/repair-seals/assign', [
            'seal_ids' => [$seal->id],
            'technician_id' => $this->technician->id,
        ]);

        $response->assertStatus(422);
    }

    // ── Transfer ───────────────────────────────────────────────

    public function test_transfer_between_technicians(): void
    {
        $tech2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $seal = InmetroSeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => InmetroSeal::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/repair-seals/transfer', [
            'seal_ids' => [$seal->id],
            'from_technician_id' => $this->technician->id,
            'to_technician_id' => $tech2->id,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('inmetro_seals', [
            'id' => $seal->id,
            'assigned_to' => $tech2->id,
        ]);

        $this->assertDatabaseHas('repair_seal_assignments', [
            'seal_id' => $seal->id,
            'technician_id' => $tech2->id,
            'action' => 'transferred',
            'previous_technician_id' => $this->technician->id,
        ]);
    }

    // ── Return ─────────────────────────────────────────────────

    public function test_return_seals_to_stock(): void
    {
        $seal = InmetroSeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => InmetroSeal::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/repair-seals/return', [
            'seal_ids' => [$seal->id],
            'reason' => 'Selo não utilizado nesta visita',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('inmetro_seals', [
            'id' => $seal->id,
            'status' => 'returned',
            'returned_reason' => 'Selo não utilizado nesta visita',
        ]);
    }

    // ── Register Usage ─────────────────────────────────────────

    public function test_register_usage_with_photo(): void
    {
        Event::fake();
        Storage::fake('public');
        Sanctum::actingAs($this->technician, ['*']);

        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);
        $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
        $seal = InmetroSeal::factory()->seloReparo()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => InmetroSeal::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/repair-seals/use', [
            'seal_id' => $seal->id,
            'work_order_id' => $wo->id,
            'equipment_id' => $equipment->id,
            'photo' => UploadedFile::fake()->image('selo.jpg', 640, 480),
        ]);

        $response->assertStatus(200);

        $seal->refresh();
        $this->assertEquals(InmetroSeal::STATUS_PENDING_PSEI, $seal->status);
        $this->assertEquals(InmetroSeal::PSEI_PENDING, $seal->psei_status);
        $this->assertNotNull($seal->used_at);
        $this->assertNotNull($seal->deadline_at);
        $this->assertNotNull($seal->photo_path);
        $this->assertEquals($wo->id, $seal->work_order_id);

        Event::assertDispatched(SealUsedOnWorkOrder::class);
    }

    public function test_register_usage_requires_photo(): void
    {
        Sanctum::actingAs($this->technician, ['*']);

        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);
        $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
        $seal = InmetroSeal::factory()->assigned($this->technician->id)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/repair-seals/use', [
            'seal_id' => $seal->id,
            'work_order_id' => $wo->id,
            'equipment_id' => $equipment->id,
            // No photo
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('photo');
    }

    public function test_register_usage_rejects_seal_from_other_technician(): void
    {
        Sanctum::actingAs($this->technician, ['*']);

        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);
        $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
        $seal = InmetroSeal::factory()->assigned($this->admin->id)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/repair-seals/use', [
            'seal_id' => $seal->id,
            'work_order_id' => $wo->id,
            'equipment_id' => $equipment->id,
            'photo' => UploadedFile::fake()->image('selo.jpg'),
        ]);

        $response->assertStatus(422);
    }

    public function test_lacre_usage_sets_status_used_not_pending_psei(): void
    {
        Event::fake();
        Storage::fake('public');
        Sanctum::actingAs($this->technician, ['*']);

        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id]);
        $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id]);
        $seal = InmetroSeal::factory()->lacre()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => InmetroSeal::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/repair-seals/use', [
            'seal_id' => $seal->id,
            'work_order_id' => $wo->id,
            'equipment_id' => $equipment->id,
            'photo' => UploadedFile::fake()->image('lacre.jpg'),
        ]);

        $response->assertStatus(200);
        $seal->refresh();
        $this->assertEquals(InmetroSeal::STATUS_USED, $seal->status);
        $this->assertEquals(InmetroSeal::PSEI_NOT_APPLICABLE, $seal->psei_status);
        $this->assertNull($seal->deadline_at);
    }

    // ── Report Damage ──────────────────────────────────────────

    public function test_report_damage_with_reason(): void
    {
        $seal = InmetroSeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => InmetroSeal::STATUS_ASSIGNED,
            'assigned_to' => $this->technician->id,
        ]);

        $response = $this->patchJson("/api/v1/repair-seals/{$seal->id}/report-damage", [
            'status' => 'damaged',
            'reason' => 'Selo rasgou durante aplicação',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('inmetro_seals', [
            'id' => $seal->id,
            'status' => 'damaged',
            'notes' => 'Selo rasgou durante aplicação',
        ]);
    }

    public function test_report_damage_requires_reason(): void
    {
        $seal = InmetroSeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => InmetroSeal::STATUS_ASSIGNED,
        ]);

        $response = $this->patchJson("/api/v1/repair-seals/{$seal->id}/report-damage", [
            'status' => 'lost',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    // ── Overdue ────────────────────────────────────────────────

    public function test_overdue_endpoint_returns_overdue_seals(): void
    {
        InmetroSeal::factory()->overdue()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
        ]);
        InmetroSeal::factory()->available()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/repair-seals/overdue');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    // ── Export ──────────────────────────────────────────────────

    public function test_export_returns_csv(): void
    {
        InmetroSeal::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->get('/api/v1/repair-seals/export');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}
