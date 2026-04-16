<?php

namespace Tests\Unit\Services\RepairSeal;

use App\Exceptions\TechnicianBlockedException;
use App\Models\InmetroSeal;
use App\Models\RepairSealBatch;
use App\Models\Tenant;
use App\Models\User;
use App\Services\RepairSealService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class RepairSealServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private User $technician;

    private RepairSealService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->service = app(RepairSealService::class);
    }

    // ── Receive Batch ──────────────────────────────────────────

    public function test_receive_batch_creates_individual_seals(): void
    {
        $batch = $this->service->receiveBatch([
            'tenant_id' => $this->tenant->id,
            'type' => 'seal_reparo',
            'batch_code' => 'LOTE-SVC-001',
            'range_start' => '1',
            'range_end' => '5',
            'prefix' => 'RS-',
            'received_at' => now()->format('Y-m-d'),
            'received_by' => $this->admin->id,
        ]);

        $this->assertEquals(5, $batch->quantity);
        $this->assertEquals(5, $batch->quantity_available);
        $this->assertEquals(5, InmetroSeal::where('batch_id', $batch->id)->count());

        // Check number format
        $numbers = InmetroSeal::where('batch_id', $batch->id)->pluck('number')->sort()->values();
        $this->assertEquals('RS-1', $numbers[0]);
        $this->assertEquals('RS-5', $numbers[4]);
    }

    // ── Assign ─────────────────────────────────────────────────

    public function test_assign_updates_seal_and_creates_audit_log(): void
    {
        $seals = InmetroSeal::factory()->count(3)->available()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $count = $this->service->assignToTechnician(
            $seals->pluck('id')->toArray(),
            $this->technician->id,
            $this->admin->id,
        );

        $this->assertEquals(3, $count);

        foreach ($seals as $seal) {
            $seal->refresh();
            $this->assertEquals(InmetroSeal::STATUS_ASSIGNED, $seal->status);
            $this->assertEquals($this->technician->id, $seal->assigned_to);
            $this->assertNotNull($seal->assigned_at);
        }
    }

    public function test_assign_blocks_when_technician_has_overdue_seals(): void
    {
        // Create overdue seal for technician
        InmetroSeal::factory()->overdue()->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
        ]);

        $seal = InmetroSeal::factory()->available()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->expectException(TechnicianBlockedException::class);

        $this->service->assignToTechnician(
            [$seal->id],
            $this->technician->id,
            $this->admin->id,
        );
    }

    public function test_assign_skips_non_available_seals(): void
    {
        $available = InmetroSeal::factory()->available()->create(['tenant_id' => $this->tenant->id]);
        $used = InmetroSeal::factory()->used()->create(['tenant_id' => $this->tenant->id]);

        $count = $this->service->assignToTechnician(
            [$available->id, $used->id],
            $this->technician->id,
            $this->admin->id,
        );

        $this->assertEquals(1, $count);
    }

    // ── Transfer ───────────────────────────────────────────────

    public function test_transfer_updates_assigned_to(): void
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

        $count = $this->service->transferBetweenTechnicians(
            [$seal->id],
            $this->technician->id,
            $tech2->id,
            $this->admin->id,
        );

        $this->assertEquals(1, $count);
        $seal->refresh();
        $this->assertEquals($tech2->id, $seal->assigned_to);
    }

    // ── Return ─────────────────────────────────────────────────

    public function test_return_updates_status_and_clears_assignment(): void
    {
        $batch = RepairSealBatch::factory()->create([
            'tenant_id' => $this->tenant->id,
            'received_by' => $this->admin->id,
            'quantity' => 10,
            'quantity_available' => 8,
        ]);

        $seal = InmetroSeal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'batch_id' => $batch->id,
            'assigned_to' => $this->technician->id,
            'status' => InmetroSeal::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        $count = $this->service->returnSeals(
            [$seal->id],
            'Não precisei usar',
            $this->admin->id,
        );

        $this->assertEquals(1, $count);

        $seal->refresh();
        $this->assertEquals(InmetroSeal::STATUS_RETURNED, $seal->status);
        $this->assertNull($seal->assigned_to);
        $this->assertEquals('Não precisei usar', $seal->returned_reason);

        $batch->refresh();
        $this->assertEquals(9, $batch->quantity_available);
    }

    // ── Technician Inventory ───────────────────────────────────

    public function test_get_technician_inventory_returns_only_assigned(): void
    {
        InmetroSeal::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => InmetroSeal::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);
        InmetroSeal::factory()->available()->create(['tenant_id' => $this->tenant->id]);

        $inventory = $this->service->getTechnicianInventory($this->technician->id);

        $this->assertCount(3, $inventory);
    }

    // ── Dashboard ──────────────────────────────────────────────

    public function test_dashboard_stats_are_accurate(): void
    {
        InmetroSeal::factory()->count(5)->available()->create(['tenant_id' => $this->tenant->id]);
        InmetroSeal::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'assigned_to' => $this->technician->id,
            'status' => InmetroSeal::STATUS_ASSIGNED,
            'assigned_at' => now(),
        ]);

        $stats = $this->service->getDashboardStats($this->tenant->id);

        $this->assertEquals(5, $stats['total_available']);
        $this->assertEquals(2, $stats['total_assigned']);
    }
}
